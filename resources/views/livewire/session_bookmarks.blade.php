<div class="grid gap-0 border-b border-white/50 w-full align-middle">
    <div class="grid gap-0 grid-cols-4">
        @foreach($app_session as $item)
            <x-icon name="{{ $item['icon'] }}" label="{{ $item['label'] }}" class="text-primary" />
        @endforeach
    </div>
</div>