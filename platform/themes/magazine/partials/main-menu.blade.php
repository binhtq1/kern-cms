<ul {!! BaseHelper::clean($options) !!}>
    @foreach ($menu_nodes as $row)
        <li class="{{ $row->has_child ? 'menu-item-has-children' : '' }} {{ $row->css_class }} {{ $row->active ? 'is-active' : '' }}">
            <a href="{{ $row->url }}" target="{{ $row->target }}" @if ($row->target === '_blank') rel="noopener noreferrer" @endif>
                <span>{{ $row->title }}</span>
            </a>
            @if ($row->has_child)
                <button type="button" class="submenu-toggle" aria-expanded="false" aria-label="{{ __('Mở menu con của :title', ['title' => $row->title]) }}" data-submenu-toggle>
                    <span aria-hidden="true"></span>
                </button>
                {!! Menu::generateMenu(['menu' => $menu, 'menu_nodes' => $row->child, 'view' => 'main-menu', 'options' => ['class' => 'sub-menu']]) !!}
            @endif
        </li>
    @endforeach
</ul>
