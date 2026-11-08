<?php

namespace App\Services;

use App\Models\Aluno;
use Illuminate\Support\Facades\URL;

class SignedDocumentService
{
    public function urlBoletim(Aluno $aluno, ?string $anoLectivo = null, ?int $trimestre = null): string
    {
        $ttl = max(1, (int) config('facilita.signed_document_ttl_hours', 24));

        // Em produção (ex.: www.facilita.it.ao), força APP_URL para o QR apontar
        // para o domínio público e não para o host local da geração.
        $rootOverride = rtrim((string) config('facilita.signed_document_url_root', ''), '/');
        if ($rootOverride !== '') {
            URL::forceRootUrl($rootOverride);
        }

        return URL::temporarySignedRoute(
            'documentos.boletim.signed',
            now()->addHours($ttl),
            array_filter([
                'aluno' => $aluno->public_uuid,
                'ano_lectivo' => $anoLectivo,
                'trimestre' => $trimestre,
            ], fn ($v) => $v !== null && $v !== '')
        );
    }
}
