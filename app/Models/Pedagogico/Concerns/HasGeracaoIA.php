<?php

namespace App\Models\Pedagogico\Concerns;

use App\Models\Pedagogico\PedGeracaoIA;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasGeracaoIA
{
    public function geracoesIA(): MorphMany
    {
        return $this->morphMany(PedGeracaoIA::class, 'geravel');
    }
}
