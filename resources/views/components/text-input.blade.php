@props(['disabled' => false])

<input
    @disabled($disabled)
    {{ $attributes->merge([
        'class' => 'rounded-md border-gray-300 bg-white text-gray-900 placeholder-gray-500 caret-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-white dark:text-gray-900 dark:placeholder-gray-500',
    ]) }}
>
