<tbody>
    @foreach ($modules as $module)
        <tr>
            <td>{{ $module['label'] }}</td>
            <td>{{ $module['hint'] }}</td>
            <td><span class="panel-badge panel-badge-pasif"><i class="fa-solid fa-minus" aria-hidden="true"></i> Henüz kayıt yok</span></td>
        </tr>
    @endforeach
</tbody>
