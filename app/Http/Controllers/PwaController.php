<?php

namespace App\Http\Controllers;

use App\Support\DeviceDetect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PWA — manifest e configuração (apenas relevante em dispositivos móveis).
 */
class PwaController extends Controller
{
    public function manifestStaff(Request $request): JsonResponse
    {
        if (! DeviceDetect::suportaPwa($request)) {
            return response()->json(['error' => 'PWA disponível apenas em dispositivos móveis.'], 404);
        }

        $prefix = trim(config('facilita.route_prefix', 'app'), '/');
        $startUrl = url('/'.($prefix ?: 'app').'/dashboard');
        $scope = '/'.($prefix ?: 'app').'/';

        return $this->manifestResponse(
            name: config('app.name').' — Facilita',
            shortName: 'Facilita',
            description: 'Sistema académico — lançamento de notas e gestão escolar',
            startUrl: $startUrl,
            scope: $scope,
        );
    }

    public function manifestPortal(Request $request): JsonResponse
    {
        if (! DeviceDetect::suportaPwa($request)) {
            return response()->json(['error' => 'PWA disponível apenas em dispositivos móveis.'], 404);
        }

        $startUrl = route('portal.aluno.dashboard');
        $scopePath = rtrim(dirname(parse_url($startUrl, PHP_URL_PATH) ?: '/portal/aluno'), '/').'/';

        return $this->manifestResponse(
            name: config('app.name').' — Portal do Aluno',
            shortName: 'Portal Aluno',
            description: 'Consulta de notas, horário e provas',
            startUrl: $startUrl,
            scope: $scopePath,
        );
    }

    private function manifestResponse(
        string $name,
        string $shortName,
        string $description,
        string $startUrl,
        string $scope,
    ): JsonResponse {
        return response()->json([
            'name' => $name,
            'short_name' => $shortName,
            'description' => $description,
            'start_url' => $startUrl,
            'scope' => $scope,
            'display' => 'standalone',
            'background_color' => '#f1f5f9',
            'theme_color' => '#4f46e5',
            'orientation' => 'portrait-primary',
            'lang' => 'pt',
            'icons' => [
                [
                    'src' => asset('images/facilitaicon.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => asset('images/facilitaicon.png'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ], 200, [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
