<section class="py-8 lg:py-16 relative isolate overflow-hidden bg-{{ $blockData['background_color'] }}">
    <x-dynamic-component :component="'container'">
        <div class="flex flex-col text-left max-w-[800px]">
            @if($blockData['toptitle'] ?? false)
                <p class="text-xs uppercase tracking-widest font-bold font-mono text-{{ $blockData['top_title_color'] }}">
                    {{ $blockData['toptitle'] }}
                </p>
            @endif

            <h2 class="mt-8 text-3xl md:text-4xl font-bold text-{{ $blockData['title_color'] }}">
                {{ $blockData['title'] }}
            </h2>

            @if($blockData['subtitle'] ?? false)
                <div class="mt-8 text-lg text-balance text-{{ $blockData['sub_title_color'] }}">
                    {!! cms()->convertToHtml($blockData['subtitle']) !!}
                </div>
            @endif

            <div class="mt-8">
                <button wire:click="submit" class="button button-solid-blue">
                    {{ $blockData['button_title'] }}
                </button>
            </div>
        </div>
    </x-dynamic-component>
</section>
