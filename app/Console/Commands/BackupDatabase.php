<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database
                            {--disk=local : Disco de armazenamento}
                            {--retention=30 : Dias de retencao de backups}';

    protected $description = 'Criar backup da base de dados MySQL';

    public function handle(): int
    {
        $this->info('Iniciando backup da base de dados...');

        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port', 3306);

        $timestamp = now()->format('Y-m-d_His');
        $filename = "backup_{$database}_{$timestamp}.sql";
        $gzFilename = "{$filename}.gz";

        $backupPath = storage_path("app/backups/{$filename}");
        $gzPath = storage_path("app/backups/{$gzFilename}");

        // Garantir que o directorio existe
        if (!file_exists(storage_path('app/backups'))) {
            mkdir(storage_path('app/backups'), 0755, true);
        }

        // Comando mysqldump
        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s %s > %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($backupPath)
        );

        $returnVar = 0;
        $output = [];
        exec($command . ' 2>&1', $output, $returnVar);

        if ($returnVar !== 0) {
            $this->error('Erro ao criar backup: ' . implode("\n", $output));
            return Command::FAILURE;
        }

        // Verificar se o ficheiro foi criado
        if (!file_exists($backupPath) || filesize($backupPath) === 0) {
            $this->error('O ficheiro de backup esta vazio ou nao foi criado.');
            return Command::FAILURE;
        }

        // Comprimir o backup
        $this->info('Comprimindo backup...');

        $fp = gzopen($gzPath, 'w9');
        if ($fp) {
            gzwrite($fp, file_get_contents($backupPath));
            gzclose($fp);
            unlink($backupPath); // Remover ficheiro nao comprimido
        } else {
            $this->warn('Nao foi possivel comprimir o backup. Mantendo ficheiro original.');
            $gzPath = $backupPath;
            $gzFilename = $filename;
        }

        $size = filesize($gzPath);
        $sizeFormatted = $this->formatBytes($size);

        $this->info("Backup criado: {$gzFilename} ({$sizeFormatted})");

        // Limpar backups antigos
        $this->limpiarBackupsAntigos($this->option('retention'));

        // Registar no log
        activity()
            ->withProperties([
                'ficheiro' => $gzFilename,
                'tamanho' => $size,
                'base_dados' => $database,
            ])
            ->log('Backup da base de dados criado');

        $this->info('Backup concluido com sucesso!');

        return Command::SUCCESS;
    }

    protected function limpiarBackupsAntigos(int $dias): void
    {
        $this->info("Limpando backups com mais de {$dias} dias...");

        $backupDir = storage_path('app/backups');
        $files = glob("{$backupDir}/backup_*.sql*");
        $dataLimite = now()->subDays($dias);
        $removidos = 0;

        foreach ($files as $file) {
            $fileTime = filemtime($file);
            if ($fileTime < $dataLimite->timestamp) {
                unlink($file);
                $removidos++;
            }
        }

        if ($removidos > 0) {
            $this->info("Removidos {$removidos} backups antigos.");
        }
    }

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
}
