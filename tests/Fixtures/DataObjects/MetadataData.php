<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\DataObjects;

class MetadataData
{
    /**
     * @param string $title
     * @param string|null $description
     * @param array<int, string> $tags
     */
    public function __construct(
        public string $title,
        public ?string $description = null,
        public array $tags = []
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? '',
            description: $data['description'] ?? null,
            tags: $data['tags'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'tags' => $this->tags,
        ];
    }
}
