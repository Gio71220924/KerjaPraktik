<ul>
    @foreach ($nodes as $node)
        <li>
            {{-- Tampilkan data node --}}
            {{ $node->atribut_name }} - {{ $node->value_name }}

            {{-- Jika node memiliki children, panggil partial ini lagi --}}
            @if (!empty($node->children))
                @include('partials.tree_node', ['nodes' => $node->children])
            @endif
        </li>
    @endforeach
</ul>
