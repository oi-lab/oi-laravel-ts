<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\DataObjects;

class AddressData
{
    /**
     * @param string $street
     * @param string $city
     * @param string|null $state
     * @param string $zipCode
     */
    public function __construct(
        public string $street,
        public string $city,
        public ?string $state = null,
        public string $zipCode = '00000'
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            street: $data['street'] ?? '',
            city: $data['city'] ?? '',
            state: $data['state'] ?? null,
            zipCode: $data['zip_code'] ?? '00000'
        );
    }

    public function toArray(): array
    {
        return [
            'street' => $this->street,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zipCode,
        ];
    }
}
