@blaze(fold: true, unsafe: ['logo:dark'])

@php $logoDark ??= $attributes->pluck('logo:dark'); @endphp

@props([
    'name' => null,
    'logo' => null,
    'logoDark' => null,
    'alt' => null,
    'href' => '/',
])

@php
$classes = Flux::classes()
    ->add('min-h-[50px] flex items-center me-4')
    ;

$textClasses = Flux::classes()
    ->add('text-sm font-medium truncate [:where(&)]:text-zinc-800 dark:[:where(&)]:text-zinc-100')
    ;
@endphp

<?php if ($name): ?>
    <a href="{{ $href }}" {{ $attributes->class([ $classes, 'gap-2' ]) }} data-flux-brand>
        <?php if ($logo instanceof \Illuminate\View\ComponentSlot): ?>
            <div {{ $logo->attributes->class('app-logo-frame flex items-center justify-center') }}>
                {{ $logo }}
            </div>
        <?php else: ?>
            <div class="app-logo-frame flex items-center justify-center">
                <?php if ($logoDark): ?>
                    <img src="{{ $logo }}" alt="{{ $alt }}" class="app-logo-img dark:hidden" />
                    <img src="{{ $logoDark }}" alt="{{ $alt }}" class="app-logo-img hidden dark:block" />
                <?php elseif ($logo): ?>
                    <img src="{{ $logo }}" alt="{{ $alt }}" class="app-logo-img" />
                <?php else: ?>
                    {{ $slot }}
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="{{ $textClasses }}">{{ $name }}</div>
    </a>
<?php else: ?>
    <a href="{{ $href }}" {{ $attributes->class($classes) }} data-flux-brand>
        <?php if ($logo instanceof \Illuminate\View\ComponentSlot): ?>
            <div {{ $logo->attributes->class('app-logo-frame flex items-center justify-center') }}>
                {{ $logo }}
            </div>
        <?php else: ?>
            <div class="app-logo-frame flex items-center justify-center">
                <?php if ($logoDark): ?>
                    <img src="{{ $logo }}" alt="{{ $alt }}" class="app-logo-img dark:hidden" />
                    <img src="{{ $logoDark }}" alt="{{ $alt }}" class="app-logo-img hidden dark:block" />
                <?php elseif ($logo): ?>
                    <img src="{{ $logo }}" alt="{{ $alt }}" class="app-logo-img" />
                <?php else: ?>
                    {{ $slot }}
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </a>
<?php endif; ?>
