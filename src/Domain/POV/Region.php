<?php

namespace App\Domain\POV;

class Region
{
    private float $bottomLeftX;
    private float $bottomLeftY;
    private float $topRightX;
    private float $topRightY;

    public function __construct(float $bottomLeftX, float $bottomLeftY, float $topRightX, float $topRightY)
    {
        $this->bottomLeftX = $bottomLeftX;
        $this->bottomLeftY = $bottomLeftY;
        $this->topRightX = $topRightX;
        $this->topRightY = $topRightY;
    }

    public function getBottomLeftX(): float
    {
        return $this->bottomLeftX;
    }

    public function getBottomLeftY(): float
    {
        return $this->bottomLeftY;
    }

    public function getTopRightX(): float
    {
        return $this->topRightX;
    }

    public function getTopRightY(): float
    {
        return $this->topRightY;
    }

    public function setBottomLeftX(float $bottomLeftX): void
    {
        $this->bottomLeftX = $bottomLeftX;
    }

    public function setBottomLeftY(float $bottomLeftY): void
    {
        $this->bottomLeftY = $bottomLeftY;
    }

    public function setTopRightX(float $topRightX): void
    {
        $this->topRightX = $topRightX;
    }

    public function setTopRightY(float $topRightY): void
    {
        $this->topRightY = $topRightY;
    }

    public function getBottomLeft(): array
    {
        return [$this->bottomLeftX, $this->bottomLeftY];
    }

    public function getTopRight(): array
    {
        return [$this->topRightX, $this->topRightY];
    }

    public function createClampedBy(Region $other): ?Region
    {
        list($inputBottomLeftX, $inputBottomLeftY, $inputTopRightX, $inputTopRightY) = array_values($other->toArray());
        list($outputBottomLeftX, $outputBottomLeftY, $outputTopRightX, $outputTopRightY) =
            array_values($this->toArray());

        // Check for no overlap
        if ($outputTopRightX <= $inputBottomLeftX || $outputBottomLeftX >= $inputTopRightX ||
            $outputTopRightY <= $inputBottomLeftY || $outputBottomLeftY >= $inputTopRightY) {
            return null; // No overlap, return null
        }

        // clamp by input coordinates.
        $outputBottomLeftX = max($outputBottomLeftX, $inputBottomLeftX);
        $outputBottomLeftY = max($outputBottomLeftY, $inputBottomLeftY);
        $outputTopRightX = min($outputTopRightX, $inputTopRightX);
        $outputTopRightY = min($outputTopRightY, $inputTopRightY);

        return new Region($outputBottomLeftX, $outputBottomLeftY, $outputTopRightX, $outputTopRightY);
    }

    public function toArray(): array
    {
        return [
            'bottomLeftX' => $this->bottomLeftX,
            'bottomLeftY' => $this->bottomLeftY,
            'topRightX' => $this->topRightX,
            'topRightY' => $this->topRightY,
        ];
    }

    public function __toString(): string
    {
        return json_encode($this->toArray());
    }
}
