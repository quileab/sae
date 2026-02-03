<div>
    @if($count > 0)
        <x-icon name="o-bell-alert" class="text-primary" label="{{ $count }}" />
    @else
        <x-icon name="o-bell" class="text-primary" />
    @endif
</div>
