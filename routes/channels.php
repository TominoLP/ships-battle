<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('game.{id}', static function () {
    return true;
});

