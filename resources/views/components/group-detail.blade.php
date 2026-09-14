@props(['label'])

<div {{ $attributes }}>
    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</dt>
    <dd class="font-medium text-gray-900 dark:text-white">{{ $slot }}</dd>
</div>
