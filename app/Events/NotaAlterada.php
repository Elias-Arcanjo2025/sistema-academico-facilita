<?php

namespace App\Events;

use App\Models\Nota;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotaAlterada
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Nota $nota,
        public int $professorId
    ) {}
}
