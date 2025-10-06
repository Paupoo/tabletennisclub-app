<form action="{{ route('admin.transactions.upload') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="file" name="ods_file" accept=".ods" required>
    <button type="submit">Importer ODS</button>
</form>
