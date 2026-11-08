<?php

use App\Support\SetupInicial;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SetupInicial::marcarConcluidoSeInstalacaoExistente();
    }

    public function down(): void
    {
        //
    }
};
