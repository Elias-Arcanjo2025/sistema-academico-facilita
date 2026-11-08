<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migra a configuração de "disciplina terminal" da tabela `disciplinas`
     * para a pivot `curso_disciplina.classe_fim` (Plano B — fonte única de verdade).
     *
     * Regra:
     *   - Se disciplinas.terminal = true e tem classe_terminal_id apontando para uma classe X
     *   - Para cada curso onde essa disciplina é leccionada:
     *       curso_disciplina.classe_fim = X.ordem  (ex.: 10, 11, 12)
     *   - Caso a pivot já tenha classe_fim definido, é preservado.
     *
     * Para disciplinas NÃO terminais, classe_fim recebe o valor de cursos.classe_max
     * (significando: a disciplina termina junto com o curso).
     */
    public function up(): void
    {
        // 1. Disciplinas marcadas como terminais → copiar para pivot dos cursos onde existem.
        $terminais = DB::table('disciplinas')
            ->leftJoin('classes', 'classes.id', '=', 'disciplinas.classe_terminal_id')
            ->where('disciplinas.terminal', true)
            ->whereNotNull('disciplinas.classe_terminal_id')
            ->select('disciplinas.id as disciplina_id', 'classes.ordem as classe_ordem')
            ->get();

        foreach ($terminais as $t) {
            DB::table('curso_disciplina')
                ->where('disciplina_id', $t->disciplina_id)
                ->whereNull('classe_fim')
                ->update(['classe_fim' => $t->classe_ordem]);
        }

        // 2. Restantes (não terminais) → classe_fim = classe_max do curso.
        $cursos = DB::table('cursos')->get(['id', 'classe_max']);
        foreach ($cursos as $c) {
            if (!$c->classe_max) {
                continue;
            }
            DB::table('curso_disciplina')
                ->where('curso_id', $c->id)
                ->whereNull('classe_fim')
                ->update(['classe_fim' => $c->classe_max]);
        }

        // 3. Garantir também classe_inicio = 10 quando vazio (default do sistema técnico).
        // Pode ser ajustado posteriormente via UI.
        DB::table('curso_disciplina')
            ->whereNull('classe_inicio')
            ->update(['classe_inicio' => 10]);
    }

    public function down(): void
    {
        // Reverter limpando os campos populados automaticamente.
        DB::table('curso_disciplina')->update([
            'classe_inicio' => null,
            'classe_fim' => null,
        ]);
    }
};
