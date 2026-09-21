<?php

namespace App\Http\Requests\Pricing;

use App\Concerns\PreparesPriceListInput;
use App\Enums\Pricing\PriceListChannel;
use App\Enums\Pricing\PriceListScope;
use App\Models\Pricing\PriceList;
use App\Rules\Pricing\NoOverlappingPriceListValidity;
use App\Rules\UniqueNormalizedValue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePriceListRequest extends FormRequest
{
    use PreparesPriceListInput;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $priceListId = $this->priceListId();

        return [
            'name' => ['required', 'string', 'min:2', 'max:100', new UniqueNormalizedValue(PriceList::class, 'name_normalized', $priceListId)],
            'description' => ['nullable', 'string'],
            'scope' => ['required', Rule::enum(PriceListScope::class)],
            'channel' => [
                Rule::requiredIf($this->isChannelScoped(...)),
                'nullable',
                Rule::enum(PriceListChannel::class),
            ],
            'valid_from' => ['required', 'date', $this->overlapRule($priceListId)],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function priceListId(): int
    {
        $priceList = $this->route('price_list');

        return $priceList instanceof PriceList ? $priceList->id : (int) $priceList;
    }

    private function overlapRule(int $priceListId): NoOverlappingPriceListValidity
    {
        return new NoOverlappingPriceListValidity(
            scope: $this->filled('scope') ? $this->string('scope')->toString() : null,
            channel: $this->filled('channel') ? $this->string('channel')->toString() : null,
            validTo: $this->filled('valid_to') ? $this->string('valid_to')->toString() : null,
            isActive: $this->boolean('is_active', true),
            ignoreId: $priceListId,
        );
    }
}
