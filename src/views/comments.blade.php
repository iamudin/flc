<div class="table-responsive">
<table class="table-striped table table-bordered table-hover  bg-white">
    <thead>
        <tr>
            <th width="20px">No</th>
            <th>Waktu</th>
            <th style="width:200px">Pengirim</th>
            <th>Isi</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($data as  $item)
            <tr>
                <td align="center">{{ $loop->iteration + ($data->currentPage() - 1) * $data->perPage() }}</td>
                <td><code>{{ $item->created_at->format('d F Y H:i T') }}</code></td>
                <td><i class="fa fa-user"></i>  {{ $item->name}}
                    <br><small class="text-muted"><i class="fa fa-link"></i> {{ $item->link ?? '-' }}
                    <br><i class="fa fa-at"></i>  {{ $item->email ?? '-' }}
                    <br><i class="fa fa-globe"></i>  {{ $item->ip ?? '-' }}
                    </small></td>
                <td>{!! $item->content !!}
                    <p><small><a href="{{ $item->reference}}">{{ url($item->reference) }}</a></small></p>
                </td>
                <td><span class="badge badge-{{ $item->status=='publish' ? 'success' : 'warning' }}">{{str( $item->status)->upper() }}</span></td>
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
@if($data)
{{ $data->links('pagination::bootstrap-5') }}
@endif

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
