<tbody>
    @foreach ($modules as $module)
        <tr>
            <td>{{ $module['label'] }}</td>
            <td>{{ $module['hint'] }}</td>
            <td><x-ui.badge tone="pasif" icon="fa-solid fa-minus">Henüz kayıt yok</x-ui.badge></td>
        </tr>
    @endforeach
</tbody>
