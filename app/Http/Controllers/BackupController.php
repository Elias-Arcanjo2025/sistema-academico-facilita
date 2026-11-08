<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    /**
     * Listar todos os backups
     */
    public function index(): View
    {
        $this->authorize('backup.viewAny');

        $backupPath = storage_path('app/backups');
        $backups = [];

        if (file_exists($backupPath)) {
            $files = glob("{$backupPath}/backup_*.sql*");
            rsort($files); // Mais recentes primeiro

            foreach ($files as $file) {
                $backups[] = [
                    'nome' => basename($file),
                    'tamanho' => filesize($file),
                    'tamanho_formatado' => $this->formatBytes(filesize($file)),
                    'data_criacao' => filemtime($file),
                    'data_formatada' => date('d/m/Y H:i:s', filemtime($file)),
                    'caminho' => $file,
                ];
            }
        }

        // Informações do agendamento
        $agendamento = [
            'horario' => '02:00',
            'frequencia' => 'Diariamente',
            'retencao' => '30 dias',
            'proximo' => now()->hour < 2
                ? today()->addHours(2)->format('d/m/Y H:i')
                : today()->addDay()->addHours(2)->format('d/m/Y H:i'),
        ];

        // Último backup do log
        $logPath = storage_path('logs/backup.log');
        $ultimoBackupLog = null;

        if (file_exists($logPath)) {
            $logLines = file($logPath);
            if (count($logLines) > 0) {
                $ultimoBackupLog = trim(end($logLines));
            }
        }

        return view('configuracoes.backups.index', compact(
            'backups',
            'agendamento',
            'ultimoBackupLog'
        ));
    }

    /**
     * Criar backup manual
     */
    public function create(): RedirectResponse
    {
        $this->authorize('backup.create');

        try {
            $this->info('Executando backup manual...');

            Artisan::call('backup:database', [
                '--retention' => 30,
            ]);

            $output = Artisan::output();

            // Registrar atividade
            activity()
                ->causedBy(auth()->user())
                ->withProperties(['tipo' => 'manual'])
                ->log('Backup manual da base de dados criado');

            return redirect()
                ->route('backups.index')
                ->with('success', 'Backup criado com sucesso! ' . $output);
        } catch (\Exception $e) {
            return redirect()
                ->route('backups.index')
                ->with('error', 'Erro ao criar backup: ' . $e->getMessage());
        }
    }

    /**
     * Download de backup
     */
    public function download(string $filename): StreamedResponse
    {
        $this->authorize('backup.viewAny');

        $filename = basename($filename);

        if (!preg_match('/^backup_.*\.sql(\.gz)?$/', $filename)) {
            abort(403, 'Nome de ficheiro inválido.');
        }

        $backupPath = storage_path('app/backups/' . $filename);

        if (!file_exists($backupPath)) {
            abort(404, 'Ficheiro de backup não encontrado.');
        }

        // Registrar atividade
        activity()
            ->causedBy(auth()->user())
            ->withProperties(['ficheiro' => $filename])
            ->log('Download de backup realizado');

        return response()->streamDownload(function () use ($backupPath) {
            echo file_get_contents($backupPath);
        }, $filename);
    }

    /**
     * Eliminar backup
     */
    public function destroy(string $filename): RedirectResponse
    {
        $this->authorize('backup.delete');

        $filename = basename($filename);

        if (!preg_match('/^backup_.*\.sql(\.gz)?$/', $filename)) {
            return redirect()->route('backups.index')->with('error', 'Nome de ficheiro inválido.');
        }

        $backupPath = storage_path('app/backups/' . $filename);

        if (!file_exists($backupPath)) {
            return redirect()
                ->route('backups.index')
                ->with('error', 'Ficheiro de backup não encontrado.');
        }

        try {
            unlink($backupPath);

            // Registrar atividade
            activity()
                ->causedBy(auth()->user())
                ->withProperties(['ficheiro' => $filename])
                ->log('Backup eliminado');

            return redirect()
                ->route('backups.index')
                ->with('success', 'Backup eliminado com sucesso!');
        } catch (\Exception $e) {
            return redirect()
                ->route('backups.index')
                ->with('error', 'Erro ao eliminar backup: ' . $e->getMessage());
        }
    }

    /**
     * Formatar bytes para leitura humana
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Helper para mensagens info
     */
    protected function info(string $message): void
    {
        // Método auxiliar para compatibilidade
    }
}
