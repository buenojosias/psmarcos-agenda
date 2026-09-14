{{-- @props(['label', 'value' => null])

<div {{ $attributes }}>
    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</dt>
    <dd class="font-medium text-gray-900 dark:text-white">{{ $value ?? $slot }}</dd>
</div> --}}

@props(['label', 'value' => null, 'is_badge' => false, 'badge_color' => null])

<div class="{{ $attributes }}">
    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</dt>
    <dd class="text-gray-700 dark:text-gray-300 font-medium">
        @if ($is_badge)
            <x-badge :text="$value" :color="$badge_color" />
        @elseif ($slot->isNotEmpty())
            {{ $slot }}
        @else
            {{ $value }}
        @endif
    </dd>
</div>