<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('classes')) {
            return;
        }

        $agora = now();
        $existentes = DB::table('classes')->pluck('codigo')->map(fn ($c) => (string) $c)->all();

        $novas = [];
        for ($n = 1; $n <= 6; $n++) {
            $codigo = (string) $n;
            if (in_array($codigo, $existentes, true)) {
                continue;
            }
            $novas[] = [
                'codigo' => $codigo,
                'nome' => $n.'ª Classe',
                'ordem' => $n,
                'activo' => false,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }

        if ($novas !== []) {
            DB::table('classes')->insert($novas);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('classes')) {
            return;
        }

        DB::table('classes')->whereIn('codigo', ['1', '2', '3', '4', '5', '6'])->delete();
    }
};
