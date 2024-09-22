<div class="table-responsive">
<table class="table-striped table table-bordered table-hover  bg-white">
    <thead>
        <tr>
            <th width="20px">No</th>
            <th>Waktu</th>
            <th>Pengirim</th>
            <th>Isi</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $key=>$item)
            <tr>
                <td align="center">{{ $key+1 }}</td>
                <td>{{ $item->created_at->format('d-m-y H:i T') }}</td>
                <td>{{ $item->name}}<br><small class="text-muted"><i class="fa fa-link"></i> {{ $item->link }}</small></td>
                <td>{!! $item->content !!}
                    <p><a href="/{{ $item->reference?->url }}">{{ url($item->reference?->url) }}</a></p>
                </td>
                <td width="140px">
                    <div class="btn-group">
                    <buttton class="btn btn-warning btn-sm "> <i class="fa fa-reply"></i> Reply</buttton>
                    <buttton class="btn btn-danger btn-sm "> <i class="fa fa-trash-alt"></i></buttton>
                </div>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

</div>
{{ $data->links('vendor.pagination.bootstrap-5') }}

<script>
    function lw_media_destroy(source){
    if(confirm('Hapus ? ')){
    $.post( "{{ route('media.destroy') }}", { _token:"{{ csrf_token() }}",media:source  })
.done(function( data ) {
    location.reload();
});
}
}
</script>
