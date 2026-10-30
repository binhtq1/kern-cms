# Kern CMS

Kern CMS được phân phối dưới dạng project Composer `binhtq1/kern-cms`, sử dụng runtime `binhtq1/kern-core`.

```bash
composer create-project binhtq1/kern-cms my-website
```

## Nâng cấp website từ 0.1.0 lên 0.3.1

Công cụ `kern-upgrade.php` hỗ trợ nâng cấp trực tiếp website đã cài bản `0.1.0` lên `0.3.1`. Công cụ tự chuyển cấu hình Composer sang `kern-core`, cập nhật dependency, chạy migration và cập nhật assets. Không cần nâng cấp qua `0.2.0` hoặc `0.3.0` trước.

Các ví dụ dưới đây dành cho máy chủ Linux và shell Bash/Zsh. Thay `/var/www/my-website` bằng thư mục gốc website có `artisan`, `composer.json` và `.env`. Thử trên bản sao website trước, rồi thực hiện trên website thật trong khung giờ bảo trì. Chạy từng khối lệnh; nếu có lỗi, dừng và xử lý trước khi sang bước tiếp theo.

### 1. Chuẩn bị

- PHP CLI và PHP chạy website đều từ **8.4** trở lên; Composer 2 có trong `PATH` và sử dụng cùng môi trường PHP phù hợp.
- Có extension `zip` cùng các extension CMS yêu cầu; `proc_open` và `allow_url_fopen` được bật. Máy chủ kết nối HTTPS được tới GitHub và Packagist.
- Website đã hoàn tất cài đặt: còn `.env`, `composer.lock`, `vendor/autoload.php`, `storage/installed` và cấu trúc gốc `0.1.0`. Giữ nguyên `APP_KEY`.
- Tài khoản chạy lệnh có quyền đọc/ghi các file website. Dung lượng trống phải đủ cho bản sao mã nguồn, `vendor`, uploads, database và các file tải về/giải nén tạm thời.
- Công cụ hỗ trợ driver `mysql`, `pgsql`, `sqlite`. MySQL hoặc MariaDB dùng driver `mysql` cần `mysqldump`; PostgreSQL cần `pg_dump` tương thích với máy chủ database. Tài khoản database phải có quyền tạo bản dump. SQLite phải là database trên đĩa. Driver `mariadb` riêng và các driver khác chưa được công cụ hỗ trợ snapshot.
- Có bản sao lưu độc lập trước khi triển khai. Dữ liệu trên S3, dịch vụ ngoài hoặc đích symlink nằm ngoài website cần được sao lưu riêng.

```bash
cd /var/www/my-website
php -v
composer --version
```

### 2. Tải đúng công cụ của release 0.3.1

Tải **release asset** từ [v0.3.1](https://github.com/binhtq1/kern-cms/releases/tag/v0.3.1). File `kern-upgrade.php` trong nhánh nguồn là template dùng khi build, không phải file để chạy nâng cấp.

```bash
curl --fail --location \
  https://github.com/binhtq1/kern-cms/releases/download/v0.3.1/kern-upgrade.php \
  --output kern-upgrade.php
```

Đối chiếu SHA-256 của công cụ đã phát hành:

```bash
printf '%s  %s\n' \
  b7765714d4148da4c335205f5e1fd784ef1ed020bb2bab7d964ca3c0c0e0bc29 \
  kern-upgrade.php | sha256sum --check -
```

Chỉ tiếp tục khi kết quả là `kern-upgrade.php: OK`. Công cụ cũng tự kiểm tra SHA-256 của các archive CMS/runtime tải về.

### 3. Kiểm tra xung đột trước khi nâng cấp

```bash
php kern-upgrade.php --to=0.3.1 --check
```

Kết quả thành công có dòng:

```text
Check passed. Website files and database were not changed.
```

`--check` kiểm tra cấu trúc website, các file mặc định đã sửa và mã nguồn plugin/theme tùy chỉnh còn phụ thuộc jQuery. Lệnh này không thay đổi website/database; kiểm tra ứng dụng, kết nối database và khả năng tạo snapshot diễn ra khi chạy nâng cấp thật. Kết quả kiểm tra tĩnh không thay thế việc thử các tích hợp tùy chỉnh trên bản staging.

Nếu công cụ liệt kê file xung đột, xử lý từng file rồi chạy lại `--check`. Chuyển thay đổi trong core sang code riêng, configuration hoặc overrides phù hợp; chuyển mã jQuery tùy chỉnh sang JavaScript thuần/React. Công cụ dừng khi phát hiện xung đột, không tự ghi đè để bỏ qua tùy chỉnh.

### 4. Tạm dừng các tiến trình ghi dữ liệu

Tạm dừng queue worker, scheduler/cron, import dữ liệu và các tiến trình nền có thể ghi database hoặc uploads bằng Supervisor, systemd hoặc bảng quản trị hosting đang dùng. Nếu nhiều instance cùng dùng database/storage, dừng ghi trên tất cả instance liên quan trong suốt quá trình nâng cấp và kiểm tra.

Chế độ bảo trì HTTP không tự dừng mọi tiến trình nền. Công cụ sẽ tự bật bảo trì trước khi tạo backup nếu website chưa ở chế độ này.

### 5. Chạy nâng cấp

```bash
php kern-upgrade.php --to=0.3.1
```

Công cụ lần lượt kiểm tra ứng dụng/database, bật bảo trì, sao lưu website và database, chuyển cấu hình Composer, cập nhật runtime/assets và chạy `cms:upgrade`. Các lệnh Composer và migration cần thiết đã nằm trong quy trình này.

Khi thành công, màn hình hiển thị:

```text
Kern CMS 0.3.1 upgraded successfully. Backup retained at ...
```

Backup trước nâng cấp nằm tại `storage/kern-upgrader/<thời-gian>-<mã>/`:

| File | Nội dung |
| --- | --- |
| `website.zip` | Mã nguồn, `vendor`, `.env`, uploads và các file thường trong website trước chuyển đổi. |
| `database.sql` | Snapshot MySQL/MariaDB, nếu dùng driver `mysql`. |
| `database.pgsql` | Snapshot PostgreSQL dạng custom archive, nếu dùng driver `pgsql`. |
| `database.sqlite` | Snapshot SQLite nhất quán, nếu dùng driver `sqlite`. |
| `files.json` | SHA-256 của ZIP/từng file và danh sách symlink cùng đích của chúng. |
| `database-info.json` | Driver database và trạng thái bảo trì trước khi công cụ bật bảo trì. |
| `status.json` | Trạng thái chạy và cờ `backup_complete`. |

Chỉ có snapshot ứng với driver đang dùng. ZIP không chứa `.git`, `node_modules`, các backup nâng cấp trước đó hoặc nội dung đích symlink. File bảo trì do chính công cụ tạo cũng được loại khỏi ZIP. Backup chứa bí mật trong `.env` và dữ liệu website: giữ riêng tư, không đưa vào Git hoặc nơi tải công khai.

### 6. Kiểm tra sau nâng cấp

```bash
composer show binhtq1/kern-core
cat storage/app/kern/upgrade.json
```

Xác nhận runtime là `0.3.1`; trạng thái có `"version": "0.3.1"` và `completed_at`. Kiểm tra đăng nhập quản trị/thành viên, nội dung, cài đặt, uploads, biểu mẫu và các plugin/theme tùy chỉnh. Sau khi kiểm tra xong, bật lại worker và scheduler.

Nếu chính công cụ đã bật bảo trì, nó sẽ tự mở lại website khi thành công. Nếu bạn đã bật bảo trì từ trước, công cụ giữ nguyên trạng thái đó; chỉ mở lại bằng `php artisan up --no-interaction` khi đã kiểm tra xong. Giữ backup cho đến khi xác nhận website ổn định.

## Khôi phục backup nếu nâng cấp thất bại

Công cụ **không tự rollback**. Sau lỗi, website có thể vẫn ở chế độ bảo trì. Không mở lại website hoặc chạy tiếp các lệnh nâng cấp một cách tự động khi trạng thái đang dở dang. Lưu thông báo lỗi và đường dẫn backup; chọn xử lý lỗi có kiểm soát hoặc khôi phục **mã nguồn và database từ cùng một lần backup trước nâng cấp**.

Khôi phục đưa dữ liệu về thời điểm snapshot. Dữ liệu phát sinh sau đó cần được giữ riêng và đối chiếu trước khi mở lại website.

### 1. Giữ website ngừng phục vụ và kiểm tra backup

Chặn truy cập bằng chế độ bảo trì của web server/reverse proxy/hosting trong suốt quá trình khôi phục; tiếp tục dừng worker, scheduler và các instance ghi dữ liệu. Lớp bảo trì bên ngoài ứng dụng cần thiết vì bạn sắp thay thư mục mã nguồn.

Trong cùng một phiên SSH, khai báo đường dẫn tuyệt đối. Ví dụ này áp dụng khi website là thư mục thật; hệ thống deploy bằng symlink/release directory cần điều chỉnh bước chuyển thư mục theo cơ chế deploy của mình.

```bash
kern_site_dir=/var/www/my-website
kern_backup_id=THAY-BANG-ID-BACKUP
kern_backup_dir="$kern_site_dir/storage/kern-upgrader/$kern_backup_id"
kern_restore_dir=/var/www/my-website.restore-0.1.0
kern_failed_dir="/var/www/my-website.failed-$(date +%Y%m%d%H%M%S)"

cat "$kern_backup_dir/status.json"
cat "$kern_backup_dir/database-info.json"
```

`backup_complete` phải là `true`, và phải có snapshot database đúng driver. Nếu `backup_complete` là `false`, công cụ chưa hoàn tất backup và chưa chuyển đổi code CMS; không dùng thư mục đó như một bản khôi phục hoàn chỉnh. Xử lý lỗi sao lưu hoặc dùng bản sao lưu độc lập.

Kiểm tra ZIP với hash được lưu trong chính backup:

```bash
php -r '
$backup = $argv[1];
$status = json_decode(file_get_contents($backup . "/status.json"), true, flags: JSON_THROW_ON_ERROR);
$files = json_decode(file_get_contents($backup . "/files.json"), true, flags: JSON_THROW_ON_ERROR);
if (($status["backup_complete"] ?? false) !== true || !hash_equals($files["archive_sha256"], hash_file("sha256", $backup . "/website.zip"))) {
    fwrite(STDERR, "Backup incomplete or ZIP checksum mismatch. Stop restoration.\n");
    exit(1);
}
echo "Backup ZIP verified.\n";
' "$kern_backup_dir"
```

Dừng nếu kiểm tra thất bại. Có thể sao chép toàn bộ thư mục backup sang vị trí riêng tư ngoài website trước khi thao tác tiếp.

### 2. Giải nén mã nguồn vào thư mục mới

Cần có lệnh `unzip`. `kern_restore_dir` và `kern_failed_dir` phải chưa tồn tại. Không giải nén chồng lên mã nguồn nâng cấp dở vì file mới của `0.3.1` có thể còn sót lại.

```bash
test ! -e "$kern_restore_dir" &&
test ! -e "$kern_failed_dir" &&
unzip -t "$kern_backup_dir/website.zip" &&
mkdir "$kern_restore_dir" &&
unzip -q "$kern_backup_dir/website.zip" -d "$kern_restore_dir" &&
mkdir -p "$kern_restore_dir/storage/app/public" \
  "$kern_restore_dir/storage/framework/cache/data" \
  "$kern_restore_dir/storage/framework/cache/locks" \
  "$kern_restore_dir/storage/framework/sessions" \
  "$kern_restore_dir/storage/framework/views" \
  "$kern_restore_dir/storage/logs" \
  "$kern_restore_dir/bootstrap/cache"
```

Giữ `.env`, `APP_KEY`, `composer.json`, `composer.lock` và `vendor` từ bản backup. ZIP không bảo đảm phục hồi đầy đủ owner/quyền thực thi; đặt lại quyền theo cấu hình máy chủ, đặc biệt quyền ghi `storage` và `bootstrap/cache` cho tài khoản PHP/web server.

### 3. Khôi phục database tương ứng

Chỉ chọn **một** nhánh theo `database-info.json`. Với MySQL/PostgreSQL, dùng **database mới, rỗng và có tên riêng**, ví dụ `kern_restore_0_1_0`; giữ database nâng cấp dở để đối chiếu. Không import vào database đang có bảng vì có thể để lại schema của phiên bản mới.

Thay `DB_HOST`, `DB_USER`, cổng và tên database trong ví dụ bằng giá trị thật. Dùng tài khoản có quyền tạo/import database hoặc nhờ quản trị viên tạo database rỗng, cấp quyền trước. Nhập mật khẩu khi client hỏi, không đưa mật khẩu trực tiếp vào lệnh.

Nếu website dùng `DB_URL` hoặc biến môi trường do hosting/container cấp, cập nhật cả những giá trị đó để chúng trỏ tới database khôi phục; chỉ sửa `DB_DATABASE` trong `.env` có thể chưa đủ. Kiểm tra kết nối hiệu lực trước khi mở website hoặc chạy worker.

**MySQL/MariaDB** — dùng charset/collation phù hợp với database gốc:

```bash
mysql -h DB_HOST -P 3306 -u DB_USER -p \
  -e 'CREATE DATABASE kern_restore_0_1_0 CHARACTER SET utf8mb4;' &&
mysql -h DB_HOST -P 3306 -u DB_USER -p kern_restore_0_1_0 \
  < "$kern_backup_dir/database.sql"
```

Nếu database rỗng đã được quản trị viên tạo, bỏ qua lệnh `CREATE DATABASE`. Sau khi import thành công, chỉnh `DB_DATABASE=kern_restore_0_1_0` và các thông số kết nối tương ứng trong `.env` của `kern_restore_dir`. Giữ `APP_KEY` cũ.

**PostgreSQL** — dùng `pg_restore` tương thích với phiên bản tạo dump:

```bash
createdb -h DB_HOST -p 5432 -U DB_USER -W \
  --template=template0 kern_restore_0_1_0 &&
pg_restore -h DB_HOST -p 5432 -U DB_USER -W \
  --exit-on-error --single-transaction --no-owner --no-privileges \
  --dbname=kern_restore_0_1_0 "$kern_backup_dir/database.pgsql"
```

Nếu database rỗng đã được tạo, bỏ qua `createdb`. Chỉ tiếp tục khi restore thành công; quản trị viên cần cấp lại quyền/extension riêng của website nếu có. Chỉnh `.env` trong `kern_restore_dir` trỏ tới database mới và giữ `APP_KEY` cũ.

**SQLite** — dùng snapshot riêng, không dùng bản file database nằm trong ZIP. Chọn tên mới chưa tồn tại để không dùng lại WAL/journal cũ:

```bash
test ! -e "$kern_restore_dir/database/restored-0.1.0.sqlite" &&
cp "$kern_backup_dir/database.sqlite" \
  "$kern_restore_dir/database/restored-0.1.0.sqlite"
```

Trong `.env` của `kern_restore_dir`, đặt `DB_CONNECTION=sqlite` và `DB_DATABASE` thành **đường dẫn sau khi chuyển thư mục**, ví dụ `/var/www/my-website/database/restored-0.1.0.sqlite`. Đặt quyền đọc/ghi database và thư mục cha cho tài khoản chạy PHP.

Tham khảo cú pháp từ [MySQL: khôi phục SQL dump](https://dev.mysql.com/doc/refman/8.4/en/reloading-sql-format-dumps.html) và [PostgreSQL: pg_restore](https://www.postgresql.org/docs/current/app-pgrestore.html).

### 4. Chuyển lại mã nguồn và khôi phục symlink

Chỉ chuyển thư mục sau khi mã nguồn đã giải nén đầy đủ và database đã khôi phục thành công. Đứng ngoài thư mục website khi đổi tên:

```bash
cd /var/www &&
test -d "$kern_site_dir" &&
test -d "$kern_restore_dir" &&
test ! -e "$kern_failed_dir" &&
mv -T "$kern_site_dir" "$kern_failed_dir" &&
mv -T "$kern_restore_dir" "$kern_site_dir" &&
kern_backup_dir="$kern_failed_dir/storage/kern-upgrader/$kern_backup_id"
```

Thư mục nâng cấp dở và toàn bộ backup vẫn ở `kern_failed_dir`. Nếu lệnh chuyển thứ hai thất bại, giữ lớp bảo trì bên ngoài và chuyển `kern_failed_dir` về đường dẫn website trước khi thử lại.

Xem mục `symlinks` trong `files.json` và tạo lại từng liên kết theo cấu hình gốc. Với liên kết chuẩn `public/storage` tới `storage/app/public`, có thể dùng lệnh `storage:link` ở bước sau. Các liên kết riêng hoặc dữ liệu đích nằm ngoài website phải được kiểm tra/khôi phục riêng; không tự trỏ chúng vào dữ liệu đang nâng cấp dở.

`.git` và `node_modules` không có trong ZIP; nếu cần, khôi phục chúng riêng từ hệ thống quản lý mã nguồn/build. Không sao chép lại toàn bộ thư mục nâng cấp dở lên website vừa phục hồi.

### 5. Làm mới cache cấu hình và kiểm tra bản đã khôi phục

Xóa riêng file cache cấu hình để lần boot tiếp theo dùng kết nối database vừa sửa trong `.env`, rồi làm mới các cache của ứng dụng:

```bash
cd "$kern_site_dir" &&
rm -f bootstrap/cache/config.php &&
php artisan config:clear --no-interaction &&
php artisan route:clear --no-interaction &&
php artisan view:clear --no-interaction &&
php artisan event:clear --no-interaction &&
php artisan down --retry=60 --no-interaction
```

Nếu website gốc dùng liên kết chuẩn `public/storage`, tạo lại sau khi đã kiểm tra danh sách symlink:

```bash
php artisan storage:link --no-interaction
```

Nạp lại PHP-FPM/OPcache theo cơ chế deploy của máy chủ để không chạy mã đã cache của phiên bản nâng cấp dở. Kiểm tra bản `0.1.0` đã phục hồi với database vừa restore: đăng nhập quản trị, nội dung, cấu hình, ảnh và chức năng tùy chỉnh. Nếu cần kiểm tra qua HTTP, giữ lớp bảo trì bên ngoài cho công chúng, chỉ cho phép người kiểm tra truy cập và tạm mở bảo trì ứng dụng. Không chạy migration, công cụ nâng cấp hoặc tạo lại `APP_KEY` trong quá trình rollback.

### 6. Mở lại website

Sau khi xác nhận mã nguồn và database đã đồng bộ, mở chế độ bảo trì ứng dụng:

```bash
php artisan up --no-interaction
```

Gỡ lớp bảo trì của web server/hosting, rồi bật lại worker và scheduler với mã nguồn đã khôi phục. Giữ thư mục nâng cấp dở, database nâng cấp dở và backup ở nơi riêng tư để phân tích nguyên nhân trước lần nâng cấp tiếp theo.

## License

[MIT](LICENSE).
