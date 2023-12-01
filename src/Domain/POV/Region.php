<?php

namespace App\Domain\POV;

class Region
{
    private float $topLeftX;
    private float $topLeftY;
    private float $bottomRightX;
    private float $bottomRightY;

    public function __construct(float $topLeftX, float $topLeftY, float $bottomRightX, float $bottomRightY)
    {
        $this->topLeftX = $topLeftX;
        $this->topLeftY = $topLeftY;
        $this->bottomRightX = $bottomRightX;
        $this->bottomRightY = $bottomRightY;
    }

    public function getTopLeftX(): float
    {
        return $this->topLeftX;
    }

    public function getTopLeftY(): float
    {
        return $this->topLeftY;
    }

    public function getBottomRightX(): float
    {
        return $this->bottomRightX;
    }

    public function getBottomRightY(): float
    {
        return $this->bottomRightY;
    }

    public function setTopLeftX(float $topLeftX): void
    {
        $this->topLeftX = $topLeftX;
    }

    public function setTopLeftY(float $topLeftY): void
    {
        $this->topLeftY = $topLeftY;
    }

    public function setBottomRightX(float $bottomRightX): void
    {
        $this->bottomRightX = $bottomRightX;
    }

    public function setBottomRightY(float $bottomRightY): void
    {
        $this->bottomRightY = $bottomRightY;
    }

    public function getTopLeft(): array
    {
        return [$this->topLeftX, $this->topLeftY];
    }

    public function getBottomRight(): array
    {
        return [$this->bottomRightX, $this->bottomRightY];
    }

    public function toArray(): array
    {
        return [
            'topLeftX' => $this->topLeftX,
            'topLeftY' => $this->topLeftY,
            'bottomRightX' => $this->bottomRightX,
            'bottomRightY' => $this->bottomRightY,
        ];
    }

    public function __toString(): string
    {
        return json_encode($this->toArray());
    }
}
