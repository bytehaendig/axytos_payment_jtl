
<div class="card">
    <div class="card-body">
        {if !empty($messages)}
            {foreach $messages as $message}
                <div class="alert alert-{$message.type}">
                    {$message.text}
                </div>
            {/foreach}
        {/if}

        <form method="post" enctype="multipart/form-data">
            {$token}
            <input type="hidden" name="save_tools" value="1">
            <input type="hidden" name="tab" value="Tools">

            <div class="form-group">
                <label for="csv_file">Upload CSV File (timestamp,id format):</label>
                <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
                <small class="form-text text-muted">
                    CSV file should contain two columns: timestamp,id (comma separated, no quotes)
                </small>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">Process CSV</button>
            </div>
        </form>
    </div>
</div>
