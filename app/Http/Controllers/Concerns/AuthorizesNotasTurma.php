<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Nota;
use App\Models\Turma;
use App\Services\TurmaEscopoService;

trait AuthorizesNotasTurma
{
    protected function assertAcessoTurma(Turma $turma): void
    {
        app(TurmaEscopoService::class)->assertAcessoTurma(auth()->user(), $turma->id);
    }

    protected function authorizeLancamentoNotasTurma(Turma $turma): void
    {
        $this->assertAcessoTurma($turma);
        $this->authorize('create', Nota::class);
    }
}
