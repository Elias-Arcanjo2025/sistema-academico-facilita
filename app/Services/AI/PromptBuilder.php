<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\File;

class PromptBuilder
{
    public function render(string $template, array $variables): string
    {
        $path = resource_path('prompts/pedagogico/'.$template.'.txt');
        if (! File::exists($path)) {
            throw new \InvalidArgumentException("Template de prompt não encontrado: {$template}");
        }

        $content = File::get($path);
        foreach ($variables as $key => $value) {
            $replacement = is_array($value) ? implode("\n", array_map('strval', $value)) : (string) $value;
            $content = str_replace('{{'.$key.'}}', $replacement, $content);
        }

        return $content;
    }

    public function systemPedagogico(): string
    {
        return 'És um assistente pedagógico especializado no sistema educativo angolano. '
            .'Gera conteúdos académicos rigorosos, claros e adequados à idade e à classe. '
            .'Respeita rigorosamente os volumes mínimos pedidos (palavras, itens, blocos por tempo de aula), quando aplicável. '
            .'Responde sempre em JSON quando solicitado, sem texto adicional. '
            .'O JSON deve ser válido: escapa aspas e usa \\n para quebras de linha dentro de strings. '
            .$this->regrasLinguagemAcademica();
    }

    /**
     * Regras de linguagem para todos os conteúdos pedagógicos gerados por IA.
     */
    public function regrasLinguagemAcademica(): string
    {
        return 'REGRAS DE LINGUAGEM E ORTOGRAFIA (obrigatório em todo o texto): '
            .'Usa exclusivamente português de Angola ou de Portugal — NUNCA português do Brasil. '
            .'Trata-se de conteúdo académico escolar: escolhe palavras com rigor, precisão e adequação à disciplina e à faixa etária. '
            .'Ortografia conforme o ensino luso-angolano e o Acordo Ortográfico, privilegiando formas consagradas nos manuais e programas oficiais '
            .'(ex.: acção/acções, actividade/actividades, objectivo/objectivos, directo/directa, facto, contacto, recepção, elucidção, baptismo, Baptista). '
            .'Não uses grafias brasileiras: ação, atividade, objetivo, direção, recepção, você, gerúndios desnecessários ou expressões coloquiais. '
            .'Termos técnicos: emprega a nomenclatura correcta da área (ex.: TIC, redes, TCP/IP) sem simplificações incorrectas. '
            .'Tom formal, claro e pedagógico, como em planificações e manuais escolares aprovados.';
    }

    public function blocoLinguagemPrompt(): string
    {
        return "LINGUAGEM: português de Angola/Portugal; vocabulário académico rigoroso; ortografia luso-angolana (acção, actividade, objectivo, etc.); nunca português do Brasil.\n\n";
    }
}
