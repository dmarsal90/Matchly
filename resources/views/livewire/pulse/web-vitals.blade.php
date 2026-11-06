<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header name="Web Vitals" x-bind:title="`Time: {{ number_format($time) }}ms; Run at: ${formatDate('{{ $runAt }}')};`" details="real users, average, past {{ $this->periodForHumans() }}">
        <x-slot:icon>
            <x-pulse::icons.arrows-left-right />
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.30s="">
        @if ($vitals->isEmpty())
            <x-pulse::no-results />
        @else
            <x-pulse::table>
                <x-pulse::thead>
                    <tr>
                        <x-pulse::th>Metric</x-pulse::th>
                        <x-pulse::th>Page</x-pulse::th>
                        <x-pulse::th class="text-right">Samples</x-pulse::th>
                        <x-pulse::th class="text-right">Avg</x-pulse::th>
                        <x-pulse::th class="text-right">Worst</x-pulse::th>
                    </tr>
                </x-pulse::thead>
                <tbody>
                    @foreach ($vitals as $row)
                        <tr wire:key="{{ $row->metric.$row->page }}">
                            <x-pulse::td>{{ $row->metric }}</x-pulse::td>
                            <x-pulse::td><code class="text-xs">{{ $row->page }}</code></x-pulse::td>
                            <x-pulse::td class="text-right">{{ number_format($row->count) }}</x-pulse::td>
                            <x-pulse::td class="text-right font-bold {{ ['good' => 'text-green-600', 'warn' => 'text-yellow-600', 'poor' => 'text-red-600'][$row->status] }}">
                                {{ $row->scale === 1 ? number_format($row->avg) : number_format($row->avg / $row->scale, 3) }}{{ $row->unit }}
                            </x-pulse::td>
                            <x-pulse::td class="text-right">{{ $row->scale === 1 ? number_format($row->max) : number_format($row->max / $row->scale, 3) }}{{ $row->unit }}</x-pulse::td>
                        </tr>
                    @endforeach
                </tbody>
            </x-pulse::table>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
