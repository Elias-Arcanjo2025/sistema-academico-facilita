<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Modelo Excel partilhado entre Alunos e Secretaria (matrículas).
 */
class AlunoImportTemplate
{
    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'numero_processo',
            'nome',
            'data_nascimento',
            'genero',
            'bi_numero',
            'turma_id',
            'pai_nome',
            'pai_telefone',
            'mae_nome',
            'mae_telefone',
            'activo',
            'atleta_alta_competicao',
            'faltou_exame_normal',
            'educacao_especial',
            'necessidades_especificas',
        ];
    }

    /**
     * @return list<list<string|int>>
     */
    public static function exemplos(): array
    {
        return [
            ['2026001', 'João Silva', '01/01/2010', 'M', '000000000LA042', '', 'António Silva', '923000001', 'Maria Silva', '923000002', '1', '0', '0', '0', ''],
            ['2026002', 'Maria Santos', '15/03/2010', 'F', '111111111LA042', '', 'Pedro Santos', '923000003', 'Ana Santos', '923000004', '1', '0', '0', '0', ''],
        ];
    }

    public static function download(string $filename = 'template_importacao_alunos.xlsx'): BinaryFileResponse
    {
        $headers = self::headers();
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray(self::exemplos(), null, 'A2');

        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle('A1:'.$lastCol.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'],
            ],
        ]);

        for ($i = 1; $i <= count($headers); $i++) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $temp = tempnam(sys_get_temp_dir(), $filename);
        $writer->save($temp);

        return response()->download($temp, $filename)->deleteFileAfterSend(true);
    }
}
