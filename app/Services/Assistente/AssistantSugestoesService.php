<?php

namespace App\Services\Assistente;

use App\Models\User;

/**
 * Sugestões de perguntas no chat, conforme perfil e página actual.
 */
class AssistantSugestoesService
{
    /**
     * @return array<int, array{texto:string, rotulo:string}>
     */
    public function paraUtilizador(User $user, ?string $routeName = null): array
    {
        $routeName = (string) $routeName;

        if ($user->hasRole('aluno')) {
            return $this->aluno($routeName);
        }

        if ($user->hasRole('encarregado')) {
            return $this->encarregado($routeName);
        }

        if ($user->hasRole('director_turma')) {
            return $this->directorTurma($routeName);
        }

        if ($user->hasRole('secretaria')) {
            return $this->secretaria($routeName);
        }

        if ($user->hasRole('inspetor')) {
            return $this->inspetor($routeName);
        }

        if ($user->hasAnyRole(['admin_full', 'directora_geral', 'gestor_academico', 'subdiretor_pedagogico'])) {
            return $this->gestao($routeName);
        }

        return $this->staff($routeName);
    }

    /**
     * @return array<int, array{texto:string, rotulo:string}>
     */
    protected function secretaria(string $routeName): array
    {
        return [
            ['rotulo' => 'Contagem de alunos', 'texto' => 'Quantos alunos activos tem a escola este ano lectivo?'],
            ['rotulo' => 'Ficha de um aluno', 'texto' => 'Mostra-me os dados do aluno com o número de processo...'],
            ['rotulo' => 'Pedidos de documentos', 'texto' => 'Como processo os pedidos de documentos feitos pelo portal?'],
            ['rotulo' => 'Matrículas', 'texto' => 'Como registo uma matrícula nova no sistema?'],
        ];
    }

    /**
     * @return array<int, array{texto:string, rotulo:string}>
     */
    protected function inspetor(string $routeName): array
    {
        return [
            ['rotulo' => 'Aproveitamento geral', 'texto' => 'Qual é o aproveitamento geral da escola este trimestre?'],
            ['rotulo' => 'Pautas certificadas', 'texto' => 'Como funciona a certificação de pautas no sistema?'],
            ['rotulo' => 'Notas em falta', 'texto' => 'Há turmas com notas por lançar este trimestre?'],
            ['rotulo' => 'Regras de aprovação', 'texto' => 'Quais são as regras de APTO e aprovação final?'],
        ];
    }

    /**
     * @return array<int, array{texto:string, rotulo:string}>
     */
    protected function gestao(string $routeName): array
    {
        return [
            ['rotulo' => 'Visão geral', 'texto' => 'Como está o aproveitamento geral da escola este trimestre?'],
            ['rotulo' => 'Lançamentos de um professor', 'texto' => 'O professor ... já lançou as notas em todas as turmas?'],
            ['rotulo' => 'Melhores alunos', 'texto' => 'Quais são os 10 melhores alunos da escola no geral?'],
            ['rotulo' => 'Notas baixas', 'texto' => 'Quais turmas têm mais alunos com notas abaixo do mínimo?'],
        ];
    }

    /**
     * @return array<int, array{texto:string, rotulo:string}>
     */
    protected function directorTurma(string $routeName): array
    {
        return [
            ['rotulo' => 'Famílias a contactar', 'texto' => 'Quais alunos da minha turma precisam de contacto com o encarregado esta semana?'],
            ['rotulo' => 'Alunos críticos', 'texto' => 'Quais alunos da minha direcção têm notas abaixo do mínimo?'],
            ['rotulo' => 'Assiduidade da turma', 'texto' => 'Como está a assiduidade das turmas que dirijo?'],
            ['rotulo' => 'Preparar conselho', 'texto' => 'Ajuda-me a preparar o briefing para o conselho de classe da minha turma.'],
        ];
    }

    /**
     * @return array<int, array{texto:string, rotulo:string}>
     */
    protected function aluno(string $routeName): array
    {
        $base = [
            ['rotulo' => 'As minhas notas', 'texto' => 'Como estão as minhas notas este trimestre?'],
            ['rotulo' => 'Os meus professores', 'texto' => 'Quem são os meus professores?'],
            ['rotulo' => 'Tipos de nota', 'texto' => 'O que significam MAC e PP nas minhas notas?'],
            ['rotulo' => 'Comparar trimestres', 'texto' => 'Comparar as minhas notas do 1º e 2º trimestre'],
        ];

        if (str_contains($routeName, 'notas')) {
            return [
                ['rotulo' => 'Explicar o meu CFD', 'texto' => 'Explica o meu CFD e o que preciso para melhorar'],
                ['rotulo' => 'O que é MAC e PP?', 'texto' => 'O que significam MAC e PP nas minhas notas?'],
                ['rotulo' => 'Positivas e negativas', 'texto' => 'Quais disciplinas tenho positivas e negativas este trimestre?'],
                ['rotulo' => 'Comparar trimestres', 'texto' => 'Comparar as minhas notas do 1º e 2º trimestre'],
            ];
        }

        if (str_contains($routeName, 'horario')) {
            return [
                ['rotulo' => 'Aulas de hoje', 'texto' => 'Que aulas tenho hoje no horário?'],
                ['rotulo' => 'Professores', 'texto' => 'Quem são os meus professores?'],
                ['rotulo' => 'Provas', 'texto' => 'Quando são as próximas provas?'],
            ];
        }

        if (str_contains($routeName, 'provas') || str_contains($routeName, 'calendario')) {
            return [
                ['rotulo' => 'Próximas provas', 'texto' => 'Quando são as próximas provas?'],
                ['rotulo' => 'Notas', 'texto' => 'Como estão as minhas notas este trimestre?'],
            ];
        }

        if (str_contains($routeName, 'assiduidade')) {
            return [
                ['rotulo' => 'As minhas faltas', 'texto' => 'Como está a minha assiduidade?'],
                ['rotulo' => 'Notas', 'texto' => 'Como estão as minhas notas este trimestre?'],
            ];
        }

        return $base;
    }

    /**
     * @return array<int, array{texto:string, rotulo:string}>
     */
    protected function encarregado(string $routeName): array
    {
        return [
            ['rotulo' => 'Notas dos educandos', 'texto' => 'Como estão as notas dos meus educandos este trimestre?'],
            ['rotulo' => 'Situação académica', 'texto' => 'Qual é a situação académica dos meus educandos?'],
            ['rotulo' => 'Os meus documentos', 'texto' => 'Os documentos que pedi à secretaria já estão prontos?'],
            ['rotulo' => 'Pedir documento', 'texto' => 'Como peço uma declaração de frequência pelo portal?'],
            ['rotulo' => 'Professores', 'texto' => 'Quem são os professores das turmas dos meus educandos?'],
        ];
    }

    /**
     * @return array<int, array{texto:string, rotulo:string}>
     */
    protected function staff(string $routeName): array
    {
        if (str_starts_with($routeName, 'notas.') || str_contains($routeName, 'lancar')) {
            return [
                ['rotulo' => 'Notas em falta', 'texto' => 'Quais alunos ainda não têm notas lançadas este trimestre?'],
                ['rotulo' => 'Como lançar MAC/PP', 'texto' => 'Como funciona o lançamento de notas MAC e PP?'],
                ['rotulo' => 'Regras do trimestre', 'texto' => 'Quais são as regras de cálculo MT e CFD neste trimestre?'],
            ];
        }

        if (str_starts_with($routeName, 'pautas.')) {
            return [
                ['rotulo' => 'Aproveitamento', 'texto' => 'Como está o aproveitamento da minha turma?'],
                ['rotulo' => 'Notas baixas', 'texto' => 'Quais alunos têm notas abaixo do mínimo?'],
                ['rotulo' => 'APTO / NÃO APTO', 'texto' => 'Como funciona a regra de APTO na pauta?'],
            ];
        }

        return [
            ['rotulo' => 'Notas em falta', 'texto' => 'Quais alunos ainda não têm notas lançadas este trimestre?'],
            ['rotulo' => 'Desempenho da turma', 'texto' => 'Como está o desempenho dos alunos da minha turma?'],
            ['rotulo' => 'Minhas disciplinas', 'texto' => 'Quais disciplinas lecciono este ano?'],
            ['rotulo' => 'Ajuda sobre notas', 'texto' => 'Como funciona o lançamento de notas MAC e PP?'],
        ];
    }
}
