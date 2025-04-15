<table class="w-full border-collapse">
    <thead>
        <tr class="bg-gray-100">
            @foreach ($headers as $header)
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">{{ $header }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody class="bg-white divide-y divide-gray-200 text-gray-500">
        @foreach ($rows as $cells)
        <tr class="hover:bg-gray-50">
            @foreach ($cells as $cell)
            <td class="px-4 py-3 whitespace-nowrap">{!! $cell !!}</td>
            @endforeach
        </tr>
        @endforeach
    </tbody>
</table>
