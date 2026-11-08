<?php

namespace Tests\Unit;

use App\Support\NotificationDestination;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class NotificationDestinationTest extends TestCase
{
    public function test_converte_url_absoluta_de_outro_host_local_para_path(): void
    {
        config(['app.url' => 'http://facilita2.test']);

        $notification = new DatabaseNotification([
            'data' => [
                'url' => 'http://facilita.test/app/mensagens-encarregados/1',
                'tipo_evento' => 'mensagem_portal',
            ],
        ]);

        $url = NotificationDestination::url($notification, '/dashboard', 'staff');

        $this->assertSame('/app/mensagens-encarregados/1', $url);
    }

    public function test_aceita_url_relativa(): void
    {
        $notification = new DatabaseNotification([
            'data' => [
                'url' => '/app/mensagens-encarregados/3',
            ],
        ]);

        $url = NotificationDestination::url($notification, '/dashboard', 'staff');

        $this->assertSame('/app/mensagens-encarregados/3', $url);
    }
}
