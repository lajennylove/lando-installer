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
if (! ($logo instanceof \Illuminate\View\ComponentSlot) && ($logo === null || $logo === '')) {
    $logo = asset('assets/rocket.png');
}
@endphp

@php
$classes = Flux::classes()
    ->add('h-12 flex items-center me-4')
    ;

$nameLabelClasses = Flux::classes()
    // Plain utilities: Flux’s dark:[:where(&)]:… selectors often don’t win in Tailwind v4 / Electron, leaving
    // light-mode zinc-800 on dark sidebars (invisible text). See sidebar bg-zinc-900 in app layout.
    ->add('min-w-0 truncate text-2xl font-medium text-zinc-900 dark:text-white')
    ;

$brandName = (string) ($name ?? '');
$brandPrefix = $brandName;
$brandSuffix = '';
if ($brandName !== '' && str_ends_with($brandName, 'Studio')) {
    $brandPrefix = substr($brandName, 0, -6);
    $brandSuffix = 'Studio';
}
@endphp

<?php if ($name): ?>
    <a href="{{ $href }}" {{ $attributes->class([ $classes, 'gap-2' ]) }} data-flux-brand>
        <?php if ($logo instanceof \Illuminate\View\ComponentSlot): ?>
            <div {{ $logo->attributes->class('flex shrink-0 items-center justify-center') }}>
                {{ $logo }}
            </div>
        <?php else: ?>
            <?php if ($logoDark): ?>
                <img src="{{ $logo }}" alt="{{ $alt }}" class="h-12 w-12 object-cover dark:hidden" />
                <img src="{{ $logoDark }}" alt="{{ $alt }}" class="h-12 w-12 object-cover hidden dark:block" />
            <?php elseif ($logo): ?>
                <img src="{{ $logo }}" alt="{{ $alt }}" class="h-12 w-12 object-cover" />
            <?php else: ?>
                {{ $slot }}
            <?php endif; ?>
        <?php endif; ?>

        <div class="{{ $nameLabelClasses }}">
            <span>{{ $brandPrefix }}@if($brandSuffix)<span class="font-bold">{{ $brandSuffix }}</span>@endif</span>
        </div>
    </a>
<?php else: ?>
    <a href="{{ $href }}" {{ $attributes->class($classes) }} data-flux-brand>
        <?php if ($logo instanceof \Illuminate\View\ComponentSlot): ?>
            <div {{ $logo->attributes->class('flex shrink-0 items-center justify-center') }}>
                {{ $logo }}
            </div>
        <?php else: ?>
            <?php if ($logoDark): ?>
                <img src="{{ $logo }}" alt="{{ $alt }}" class="h-12 w-12 object-cover dark:hidden" />
                <img src="{{ $logoDark }}" alt="{{ $alt }}" class="h-12 w-12 object-cover hidden dark:block" />
            <?php elseif ($logo): ?>
                <img src="{{ $logo }}" alt="{{ $alt }}" class="h-12 w-12 object-cover" />
            <?php else: ?>
                {{ $slot }}
            <?php endif; ?>
        <?php endif; ?>
    </a>
<?php endif; ?>
