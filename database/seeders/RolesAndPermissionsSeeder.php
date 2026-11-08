<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Utilizadores
            'utilizadores.ver',
            'utilizadores.criar',
            'utilizadores.editar',
            'utilizadores.eliminar',
            'utilizadores.aprovar',
            'utilizadores.gerir_cargos',

            // Cursos
            'cursos.ver',
            'cursos.criar',
            'cursos.editar',
            'cursos.eliminar',

            // Disciplinas
            'disciplinas.ver',
            'disciplinas.criar',
            'disciplinas.editar',
            'disciplinas.eliminar',

            // Salas
            'salas.ver',
            'salas.criar',
            'salas.editar',
            'salas.eliminar',

            // Turnos
            'turnos.ver',
            'turnos.criar',
            'turnos.editar',
            'turnos.eliminar',

            // Turmas
            'turmas.ver',
            'turmas.criar',
            'turmas.editar',
            'turmas.eliminar',

            // Alunos
            'alunos.ver',
            'alunos.criar',
            'alunos.editar',
            'alunos.eliminar',

            // Notas
            'notas.ver',
            'notas.lancar',
            'notas.editar',
            'notas.eliminar',
            'notas.supervisionar',
            'notas.publicar',

            // Pautas (administrativas - completas)
            'pautas.ver',
            'pautas.exportar',
            'pautas.aprovar',

            // Mini-Pautas (para professores - suas disciplinas)
            'mini-pautas.ver',
            'mini-pautas.exportar',

            // Boletins
            'boletins.ver',
            'boletins.exportar',

            // Associações
            'associacoes.ver',
            'associacoes.gerir',

            // Configurações
            'configuracoes.ver',
            'configuracoes.editar',

            // Auditoria
            'auditoria.ver',
            'auditoria.exportar',

            // Arquivos de Pautas
            'pauta_arquivos.ver',
            'pauta_arquivos.criar',
            'pauta_arquivos.editar',
            'pauta_arquivos.eliminar',
            'pauta_arquivos.download',
            'pauta_arquivos.force_delete',

            // Conselhos de Notas
            'conselhos.ver',
            'conselhos.gerenciar',

            // Relatório Académico
            'relatorios.academico',
            'relatorios.disciplina',

            // Assistente Pedagógico
            'pedagogico.ver',
            'pedagogico.planificacao.criar',
            'pedagogico.planificacao.editar',
            'pedagogico.ia.gerar',
            'pedagogico.correcao.usar',
            'pedagogico.dashboard.ver',
            'pedagogico.config',
            'pedagogico.aprovar',
            'pedagogico.banco.ver',
            'pedagogico.banco.gerir',
            'orientacoes.ver',
            'orientacoes.gerir',
            'disciplina.avisar',

            // Assistente Virtual
            'assistente.usar',

            // Portal do Aluno
            'portal.aluno.ver',

            // Portal do Encarregado
            'portal.encarregado.ver',

            // Horários e calendário de provas
            'horarios.ver',
            'horarios.gerir',
            'horarios.aprovar',
            'calendario_provas.ver',
            'calendario_provas.gerir',
            'calendario_provas.ia.gerar',

            // Secretaria académica (sem finanças)
            'secretaria.ver',
            'secretaria.matriculas.gerir',
            'secretaria.documentos.emitir',
            'secretaria.cartao.configurar',
            'secretaria.alunos.documentos',
            'secretaria.campanhas.enviar',

            // Assiduidade e disciplina escolar
            'presencas.ver',
            'presencas.lancar',
            'presencas.justificar',
            'ocorrencias.ver',
            'ocorrencias.registar',
            'ocorrencias.resolver',

            // Agenda escolar e materiais
            'agenda.ver',
            'agenda.gerir',
            'materiais.ver',
            'materiais.gerir',
            'materiais.download',

            // Conteúdos mínimos oficiais
            'conteudos.ver',
            'conteudos.gerir',
            'conteudos.publicar',

            // Comunicação do director de turma com encarregados
            'turma.comunicar',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Cargo: Professor (permissões mínimas)
        $professor = Role::firstOrCreate(['name' => 'professor']);
        $professor->syncPermissions([
            'notas.ver',
            'notas.lancar',
            'notas.editar',
            'mini-pautas.ver',
            'mini-pautas.exportar',
            'alunos.ver',
            'conselhos.ver',
            'pedagogico.ver',
            'pedagogico.planificacao.criar',
            'pedagogico.planificacao.editar',
            'pedagogico.ia.gerar',
            'pedagogico.correcao.usar',
            'assistente.usar',
            'presencas.ver',
            'presencas.lancar',
            'ocorrencias.ver',
            'ocorrencias.registar',
            'pedagogico.banco.ver',
            'agenda.ver',
            'materiais.ver',
            'materiais.download',
            'conteudos.ver',
            'orientacoes.ver',
            'turma.comunicar',
        ]);

        // Cargo: Director de Turma — acompanhamento da(s) turma(s); sem lançar notas
        $directorTurma = Role::firstOrCreate(['name' => 'director_turma']);
        $directorTurma->syncPermissions([
            'notas.ver',
            'mini-pautas.ver',
            'mini-pautas.exportar',
            'pautas.ver',
            'pautas.exportar',
            'boletins.ver',
            'boletins.exportar',
            'alunos.ver',
            'alunos.editar',
            'turmas.ver',
            'disciplinas.ver',
            'cursos.ver',
            'pauta_arquivos.ver',
            'pauta_arquivos.download',
            'conselhos.ver',
            'conselhos.gerenciar',
            'pedagogico.ver',
            'pedagogico.dashboard.ver',
            'assistente.usar',
            'presencas.ver',
            'presencas.lancar',
            'presencas.justificar',
            'ocorrencias.ver',
            'ocorrencias.registar',
            'ocorrencias.resolver',
            'agenda.ver',
            'agenda.gerir',
            'materiais.ver',
            'materiais.download',
            'turma.comunicar',
        ]);

        // Cargo: Coordenador de Curso
        $coordenadorCurso = Role::firstOrCreate(['name' => 'coordenador_curso']);
        $coordenadorCurso->syncPermissions([
            'notas.ver',
            'notas.lancar',
            'notas.editar',
            'mini-pautas.ver',
            'mini-pautas.exportar',
            'pautas.ver',
            'pautas.exportar',
            'boletins.ver',
            'boletins.exportar',
            'alunos.ver',
            'alunos.criar',
            'alunos.editar',
            'turmas.ver',
            'turmas.criar',
            'turmas.editar',
            'disciplinas.ver',
            'disciplinas.criar',
            'disciplinas.editar',
            'cursos.ver',
            'cursos.criar',
            'cursos.editar',
            'salas.ver',
            'salas.criar',
            'salas.editar',
            'turnos.ver',
            'turnos.criar',
            'turnos.editar',
            'associacoes.ver',
            'associacoes.gerir',
            'pauta_arquivos.ver',
            'pauta_arquivos.criar',
            'pauta_arquivos.editar',
            'pauta_arquivos.eliminar',
            'pauta_arquivos.download',
            'conselhos.ver',
            'conselhos.gerenciar',
            'relatorios.academico',
            'pedagogico.ver',
            'pedagogico.planificacao.criar',
            'pedagogico.planificacao.editar',
            'pedagogico.ia.gerar',
            'pedagogico.correcao.usar',
            'pedagogico.dashboard.ver',
            'assistente.usar',
            'horarios.ver',
            'horarios.gerir',
            'calendario_provas.ver',
            'calendario_provas.gerir',
            'calendario_provas.ia.gerar',
            'presencas.ver',
            'presencas.lancar',
            'presencas.justificar',
            'ocorrencias.ver',
            'ocorrencias.registar',
            'ocorrencias.resolver',
            'pedagogico.aprovar',
            'pedagogico.banco.ver',
            'pedagogico.banco.gerir',
            'agenda.ver',
            'agenda.gerir',
            'materiais.ver',
            'materiais.gerir',
            'materiais.download',
            'conteudos.ver',
            'turma.comunicar',
        ]);

        // Cargo: Director Pedagógico (slug legado: subdiretor_pedagogico) — supervisão, SEM lançar notas
        $subdirectorPedagogico = Role::firstOrCreate(['name' => 'subdiretor_pedagogico']);
        $subdirectorPedagogico->syncPermissions([
            'utilizadores.ver',
            'cursos.ver',
            'disciplinas.ver',
            'salas.ver',
            'turnos.ver',
            'turmas.ver',
            'alunos.ver',
            'notas.ver',
            'notas.publicar',
            'mini-pautas.ver',
            'mini-pautas.exportar',
            'pautas.ver',
            'pautas.exportar',
            'pautas.aprovar',
            'boletins.ver',
            'boletins.exportar',
            'associacoes.ver',
            'configuracoes.ver',
            'auditoria.ver',
            'auditoria.exportar',
            'pauta_arquivos.ver',
            'pauta_arquivos.download',
            'conselhos.ver',
            'relatorios.academico',
            'pedagogico.ver',
            'pedagogico.dashboard.ver',
            'assistente.usar',
            'horarios.ver',
            'horarios.aprovar',
            'calendario_provas.ver',
            'secretaria.ver',
            'secretaria.documentos.emitir',
            'presencas.ver',
            'presencas.justificar',
            'ocorrencias.ver',
            'ocorrencias.resolver',
            'pedagogico.aprovar',
            'pedagogico.banco.ver',
            'pedagogico.banco.gerir',
            'agenda.ver',
            'agenda.gerir',
            'materiais.ver',
            'materiais.gerir',
            'materiais.download',
            'conteudos.ver',
            'conteudos.gerir',
            'conteudos.publicar',
            'turma.comunicar',
        ]);

        // Cargo: Coordenador de Disciplina — aprovação pedagógica + banco (sem lançar notas)
        $coordenadorDisciplina = Role::firstOrCreate(['name' => 'coordenador_disciplina']);
        $coordenadorDisciplina->syncPermissions([
            'pedagogico.ver',
            'pedagogico.dashboard.ver',
            'pedagogico.aprovar',
            'pedagogico.banco.ver',
            'pedagogico.banco.gerir',
            'pedagogico.ia.gerar',
            'disciplinas.ver',
            'turmas.ver',
            'alunos.ver',
            'assistente.usar',
            'agenda.ver',
            'agenda.gerir',
            'materiais.ver',
            'materiais.gerir',
            'materiais.download',
            'conteudos.ver',
            'conteudos.gerir',
            'notas.supervisionar',
            'relatorios.disciplina',
            'orientacoes.ver',
            'orientacoes.gerir',
            'disciplina.avisar',
            'conteudos.publicar',
            'boletins.ver',
            'boletins.exportar',
            'notas.ver',
            'turma.comunicar',
        ]);

        // Cargo: Inspetor — assiduidade e disciplina (sem lançar notas)
        $inspetor = Role::firstOrCreate(['name' => 'inspetor']);
        $inspetor->syncPermissions([
            'presencas.ver',
            'presencas.lancar',
            'presencas.justificar',
            'ocorrencias.ver',
            'ocorrencias.registar',
            'ocorrencias.resolver',
            'alunos.ver',
            'turmas.ver',
            'disciplinas.ver',
            'cursos.ver',
            'horarios.ver',
            'assistente.usar',
        ]);

        // Cargo: Secretária (matrículas, documentos académicos, cartão — sem finanças/notas)
        $secretaria = Role::firstOrCreate(['name' => 'secretaria']);
        $secretaria->syncPermissions([
            'secretaria.ver',
            'secretaria.matriculas.gerir',
            'secretaria.documentos.emitir',
            'secretaria.cartao.configurar',
            'secretaria.alunos.documentos',
            'secretaria.campanhas.enviar',
            'alunos.ver',
            'alunos.criar',
            'alunos.editar',
            'turmas.ver',
            'turmas.criar',
            'turmas.editar',
            'cursos.ver',
            'disciplinas.ver',
            'assistente.usar',
            'agenda.ver',
            'agenda.gerir',
            'materiais.ver',
        ]);

        // Cargo: Directora Geral (acesso administrativo completo, exceto configurações técnicas)
        $directoraGeral = Role::firstOrCreate(['name' => 'directora_geral']);
        $directoraGeral->syncPermissions([
            // Utilizadores - Gestão completa
            'utilizadores.ver',
            'utilizadores.criar',
            'utilizadores.editar',
            'utilizadores.eliminar',
            'utilizadores.aprovar',
            'utilizadores.gerir_cargos',

            // Cursos - Gestão completa
            'cursos.ver',
            'cursos.criar',
            'cursos.editar',
            'cursos.eliminar',

            // Disciplinas - Gestão completa
            'disciplinas.ver',
            'disciplinas.criar',
            'disciplinas.editar',
            'disciplinas.eliminar',

            // Salas - Gestão completa
            'salas.ver',
            'salas.criar',
            'salas.editar',
            'salas.eliminar',

            // Turnos - Gestão completa
            'turnos.ver',
            'turnos.criar',
            'turnos.editar',
            'turnos.eliminar',

            // Turmas - Gestão completa
            'turmas.ver',
            'turmas.criar',
            'turmas.editar',
            'turmas.eliminar',

            // Alunos - Gestão completa
            'alunos.ver',
            'alunos.criar',
            'alunos.editar',

            // Notas - Gestão completa
            'notas.ver',
            'notas.lancar',
            'notas.editar',
            'notas.eliminar',
            'notas.publicar',

            // Mini-Pautas - Ver de todos os professores
            'mini-pautas.ver',
            'mini-pautas.exportar',

            // Pautas - Acesso completo
            'pautas.ver',
            'pautas.exportar',
            'pautas.aprovar',

            // Boletins - Acesso completo
            'boletins.ver',
            'boletins.exportar',

            // Associações - Gestão completa
            'associacoes.ver',
            'associacoes.gerir',

            // Configurações - Apenas visualização (sem editar configurações técnicas)
            'configuracoes.ver',
            // Nota: SEM 'configuracoes.editar' - isso fica apenas para Admin Full

            // Auditoria - Acesso completo
            'auditoria.ver',
            'auditoria.exportar',

            // Arquivos de Pautas - Gestão completa
            'pauta_arquivos.ver',
            'pauta_arquivos.criar',
            'pauta_arquivos.editar',
            'pauta_arquivos.eliminar',
            'pauta_arquivos.download',
            // Nota: SEM 'pauta_arquivos.force_delete' - isso fica apenas para Admin Full

            // Conselhos - Gestão completa
            'conselhos.ver',
            'conselhos.gerenciar',

            // Relatório Académico
            'relatorios.academico',
            'relatorios.disciplina',
            'notas.supervisionar',
            'orientacoes.ver',
            'orientacoes.gerir',
            'disciplina.avisar',

            // Assistente Pedagógico
            'pedagogico.ver',
            'pedagogico.planificacao.criar',
            'pedagogico.planificacao.editar',
            'pedagogico.ia.gerar',
            'pedagogico.correcao.usar',
            'pedagogico.dashboard.ver',
            'assistente.usar',
            'horarios.ver',
            'horarios.gerir',
            'horarios.aprovar',
            'calendario_provas.ver',
            'calendario_provas.gerir',
            'calendario_provas.ia.gerar',
            'secretaria.ver',
            'secretaria.matriculas.gerir',
            'secretaria.documentos.emitir',
            'secretaria.cartao.configurar',
            'secretaria.alunos.documentos',
            'secretaria.campanhas.enviar',
            'presencas.ver',
            'presencas.lancar',
            'presencas.justificar',
            'ocorrencias.ver',
            'ocorrencias.registar',
            'ocorrencias.resolver',
            'pedagogico.aprovar',
            'pedagogico.banco.ver',
            'pedagogico.banco.gerir',
            'agenda.ver',
            'agenda.gerir',
            'materiais.ver',
            'materiais.gerir',
            'materiais.download',
            'conteudos.ver',
            'conteudos.gerir',
            'conteudos.publicar',
            'turma.comunicar',
        ]);

        // Cargo: Gestor Académico — controlo total académico em todos os cursos
        // (notas, mini-pautas, pautas, boletins, conselhos; sem gestão de utilizadores)
        $gestorAcademico = Role::firstOrCreate(['name' => 'gestor_academico']);
        $gestorAcademico->syncPermissions([
            'cursos.ver', 'cursos.criar', 'cursos.editar', 'cursos.eliminar',
            'disciplinas.ver', 'disciplinas.criar', 'disciplinas.editar', 'disciplinas.eliminar',
            'salas.ver', 'salas.criar', 'salas.editar', 'salas.eliminar',
            'turnos.ver', 'turnos.criar', 'turnos.editar', 'turnos.eliminar',
            'turmas.ver', 'turmas.criar', 'turmas.editar', 'turmas.eliminar',
            'alunos.ver', 'alunos.criar', 'alunos.editar', 'alunos.eliminar',
            'notas.ver', 'notas.lancar', 'notas.editar', 'notas.eliminar', 'notas.publicar',
            'mini-pautas.ver', 'mini-pautas.exportar',
            'pautas.ver', 'pautas.exportar', 'pautas.aprovar',
            'boletins.ver', 'boletins.exportar',
            'associacoes.ver', 'associacoes.gerir',
            'configuracoes.ver',
            'pauta_arquivos.ver', 'pauta_arquivos.criar', 'pauta_arquivos.editar',
            'pauta_arquivos.eliminar', 'pauta_arquivos.download',
            'conselhos.ver', 'conselhos.gerenciar',
            'relatorios.academico',
            'relatorios.disciplina',
            'notas.supervisionar',
            'orientacoes.ver',
            'orientacoes.gerir',
            'disciplina.avisar',
            'pedagogico.ver', 'pedagogico.planificacao.criar', 'pedagogico.planificacao.editar',
            'pedagogico.ia.gerar', 'pedagogico.correcao.usar', 'pedagogico.dashboard.ver',
            'assistente.usar',
            'horarios.ver', 'horarios.gerir', 'horarios.aprovar',
            'calendario_provas.ver', 'calendario_provas.gerir', 'calendario_provas.ia.gerar',
            'secretaria.ver', 'secretaria.matriculas.gerir', 'secretaria.documentos.emitir',
            'secretaria.cartao.configurar', 'secretaria.alunos.documentos', 'secretaria.campanhas.enviar',
            'presencas.ver', 'presencas.lancar', 'presencas.justificar',
            'ocorrencias.ver', 'ocorrencias.registar', 'ocorrencias.resolver',
            'pedagogico.aprovar', 'pedagogico.banco.ver', 'pedagogico.banco.gerir',
            'agenda.ver', 'agenda.gerir', 'materiais.ver', 'materiais.gerir', 'materiais.download',
            'conteudos.ver', 'conteudos.gerir', 'conteudos.publicar',
            'turma.comunicar',
        ]);

        // Cargo: Admin Full
        $adminFull = Role::firstOrCreate(['name' => 'admin_full']);
        // Admin Full tem acesso a TUDO via Gate::before, mas vamos sincronizar todas as permissões
        $adminFull->syncPermissions(Permission::all());

        // Cargo: Aluno (portal estudantil)
        $aluno = Role::firstOrCreate(['name' => 'aluno']);
        $aluno->syncPermissions([
            'portal.aluno.ver',
            'assistente.usar',
        ]);

        // Cargo: Encarregado de Educação
        $encarregado = Role::firstOrCreate(['name' => 'encarregado']);
        $encarregado->syncPermissions([
            'portal.encarregado.ver',
            'assistente.usar',
        ]);

        // Contas iniciais: password via FACILITA_SEED_*_PASSWORD ou aleatória.
        // Sempre exige alteração no primeiro login (must_change_password).
        $adminPassword = env('FACILITA_SEED_ADMIN_PASSWORD') ?: \Illuminate\Support\Str::password(20);
        $gestorPassword = env('FACILITA_SEED_GESTOR_PASSWORD') ?: \Illuminate\Support\Str::password(20);

        $admin = User::firstOrCreate(
            ['email' => 'admin@facilita.ao'],
            [
                'name' => 'Administrador',
                'password' => Hash::make($adminPassword),
                'estado' => 'activo',
                'must_change_password' => true,
            ]
        );
        if ($admin->wasRecentlyCreated && ! env('FACILITA_SEED_ADMIN_PASSWORD')) {
            \Illuminate\Support\Facades\Log::warning('Seeder: password inicial do admin (altere no 1.º login)', [
                'email' => $admin->email,
                'password' => $adminPassword,
            ]);
        }
        $admin->assignRole('admin_full');

        $gestor = User::firstOrCreate(
            ['email' => 'gestor@facilita.ao'],
            [
                'name' => 'Gestor Académico',
                'password' => Hash::make($gestorPassword),
                'estado' => 'activo',
                'must_change_password' => true,
            ]
        );
        if ($gestor->wasRecentlyCreated && ! env('FACILITA_SEED_GESTOR_PASSWORD')) {
            \Illuminate\Support\Facades\Log::warning('Seeder: password inicial do gestor (altere no 1.º login)', [
                'email' => $gestor->email,
                'password' => $gestorPassword,
            ]);
        }
        $gestor->syncRoles(['gestor_academico']);

        activity()
            ->causedBy($admin)
            ->log('Seeder: Roles e permissões criados com sucesso');
    }
}
