<div class="wrap">
    <h1>Wisdom Rain Player Auto Importer</h1>
    <?php settings_errors('wrpai_csv'); ?>
    <p>CSV yüklemesi için aşağıdaki formu kullanın.</p>

    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('wrpai_csv_upload', 'wrpai_nonce'); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="wrpai_csv">CSV File</label></th>
                <td><input type="file" name="wrpai_csv" accept=".csv" required></td>
            </tr>
        </table>

        <?php submit_button('Upload CSV'); ?>
    </form>

    <hr>

    <?php if ( isset($_GET['wrpai_status']) ) : ?>
        <div class="notice notice-success">
            <p><strong>CSV başarıyla yüklendi.</strong></p>
        </div>
    <?php endif; ?>
</div>
