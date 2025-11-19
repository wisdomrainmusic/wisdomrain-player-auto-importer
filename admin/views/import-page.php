<div class="wrap">
    <h1>Wisdom Rain Player Auto Importer</h1>

    <?php if ( isset($_GET['status']) && $_GET['status'] === 'uploaded' ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>CSV başarıyla yüklendi.</strong></p>
        </div>
    <?php endif; ?>

    <?php if ( isset($_GET['groups']) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <strong>Import tamamlandı:</strong><br>
                Gruplar: <?php echo intval($_GET['groups']); ?><br>
                Audio Player oluşturulan: <?php echo intval($_GET['audio']); ?><br>
                PDF Reader oluşturulan: <?php echo intval($_GET['pdf']); ?>
            </p>
        </div>
    <?php endif; ?>

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
</div>
