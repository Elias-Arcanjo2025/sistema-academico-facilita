<?php

use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encarregados', function (Blueprint $table) {
            $table->string('telefone_normalizado', 20)->nullable()->after('telefone');
            $table->index('telefone_normalizado');
        });

        $whatsapp = app(WhatsAppService::class);

        DB::table('encarregados')
            ->whereNotNull('telefone')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($whatsapp) {
                foreach ($rows as $row) {
                    DB::table('encarregados')
                        ->where('id', $row->id)
                        ->update([
                            'telefone_normalizado' => $whatsapp->normalizePhone((string) $row->telefone),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('encarregados', function (Blueprint $table) {
            $table->dropIndex(['telefone_normalizado']);
            $table->dropColumn('telefone_normalizado');
        });
    }
};
