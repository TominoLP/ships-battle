<?php

namespace App\Services;

use Illuminate\Support\Collection;
use InvalidArgumentException;

class PlacementService
{
    public function __construct(private int $boardSize = 12) {}

    public function emptyBoard(): array
    {
        return array_fill(0, $this->boardSize, array_fill(0, $this->boardSize, 0));
    }

    public function validateFleet(Collection|array $ships): array
    {
        $collection = $ships instanceof Collection ? $ships : collect($ships);

        $expected = [5 => 1, 4 => 2, 3 => 3, 2 => 4];
        $counts = $collection->groupBy('size')->map->count()->all();
        foreach ($expected as $size => $need) {
            if (($counts[$size] ?? 0) !== $need) {
                throw new InvalidArgumentException("Invalid fleet: need {$need} of size {$size}");
            }
        }

        if ($collection->count() !== array_sum($expected)) {
            throw new InvalidArgumentException('Invalid fleet count');
        }

        $board = $this->emptyBoard();

        foreach ($collection as $ship) {
            $x = (int) ($ship['x'] ?? 0);
            $y = (int) ($ship['y'] ?? 0);
            $size = (int) ($ship['size'] ?? 0);
            $dir = (string) ($ship['dir'] ?? 'H');

            if (! $this->canPlace($board, $x, $y, $size, $dir)) {
                throw new InvalidArgumentException('Ship placement invalid (bounds/overlap/touch)');
            }

            $board = $this->applyShip($board, $x, $y, $size, $dir);
        }

        return $board;
    }

    public function canPlace(array $board, int $x, int $y, int $size, string $dir): bool
    {
        for ($i = 0; $i < $size; $i++) {
            $cx = $dir === 'H' ? $x + $i : $x;
            $cy = $dir === 'V' ? $y + $i : $y;
            if (! $this->inBounds($cx, $cy)) {
                return false;
            }
            if (($board[$cy][$cx] ?? 0) !== 0) {
                return false;
            }
            for ($dy = -1; $dy <= 1; $dy++) {
                for ($dx = -1; $dx <= 1; $dx++) {
                    $nx = $cx + $dx;
                    $ny = $cy + $dy;
                    if ($this->inBounds($nx, $ny) && ($board[$ny][$nx] ?? 0) === 1) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    public function applyShip(array $board, int $x, int $y, int $size, string $dir): array
    {
        for ($i = 0; $i < $size; $i++) {
            $cx = $dir === 'H' ? $x + $i : $x;
            $cy = $dir === 'V' ? $y + $i : $y;
            $board[$cy][$cx] = 1;
        }

        return $board;
    }

    public function randomFleet(): array
    {
        $palette = [];
        foreach ([5 => 1, 4 => 2, 3 => 3, 2 => 4] as $size => $count) {
            for ($i = 0; $i < $count; $i++) {
                $palette[] = $size;
            }
        }

        $board = $this->emptyBoard();
        $ships = [];

        foreach ($palette as $size) {
            $placed = false;
            $tries = 0;
            while (! $placed) {
                if ($tries++ > 1000) {
                    throw new InvalidArgumentException('Unable to place fleet randomly');
                }

                $dir = random_int(0, 1) === 0 ? 'H' : 'V';
                $x = random_int(0, $this->boardSize - 1);
                $y = random_int(0, $this->boardSize - 1);
                if ($this->canPlace($board, $x, $y, $size, $dir)) {
                    $board = $this->applyShip($board, $x, $y, $size, $dir);
                    $ships[] = compact('x', 'y', 'size', 'dir');
                    $placed = true;
                }
            }
        }

        return ['board' => $board, 'ships' => $ships];
    }

    public function collectShipSpan(array $board, int $x, int $y): array
    {
        $cells = [[$x, $y]];

        $horiz = $this->isShipCell($board, $x - 1, $y) || $this->isShipCell($board, $x + 1, $y);
        $vert = $this->isShipCell($board, $x, $y - 1) || $this->isShipCell($board, $x, $y + 1);

        if ($horiz) {
            for ($cx = $x - 1; $this->isShipCell($board, $cx, $y); $cx--) {
                $cells[] = [$cx, $y];
            }
            for ($cx = $x + 1; $this->isShipCell($board, $cx, $y); $cx++) {
                $cells[] = [$cx, $y];
            }
        } elseif ($vert) {
            for ($cy = $y - 1; $this->isShipCell($board, $x, $cy); $cy--) {
                $cells[] = [$x, $cy];
            }
            for ($cy = $y + 1; $this->isShipCell($board, $x, $cy); $cy++) {
                $cells[] = [$x, $cy];
            }
        }

        return $cells;
    }

    private function isShipCell(array $board, int $x, int $y): bool
    {
        return isset($board[$y][$x]) && ($board[$y][$x] === 1 || $board[$y][$x] === 2);
    }

    private function inBounds(int $x, int $y): bool
    {
        return $x >= 0 && $x < $this->boardSize && $y >= 0 && $y < $this->boardSize;
    }
}
