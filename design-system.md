# Design system tham chiếu — Kern Admin

> Dành cho project Next.js + Tailwind CSS + shadcn/ui muốn kế thừa thiết kế UI/UX của admin Kern CMS.
> Ngày khảo sát: 19/10/2026. Mốc mã nguồn: `7095745`.
> Phương pháp: đọc stylesheet, component, layout và các màn hình đại diện. Chưa đối chiếu trên trình duyệt hoặc chụp ảnh giao diện đang chạy; chưa kiểm thử accessibility thực tế.

## 1. Cách sử dụng tài liệu

Tài liệu này độc lập với backend và có thể mang nguyên file sang project mới. Các thông số bên dưới đủ để dựng lại phong cách mặc định mà không cần sao chép Laravel, Inertia, cơ chế session hay cấu trúc module của Kern CMS.

Quy ước về mức độ xác thực:

- **Hiện trạng:** giá trị hoặc hành vi có trong code đã khảo sát. Các bảng thông số và mô tả component mặc định thuộc nhóm này.
- **Diễn giải thiết kế:** cách tổng hợp đặc trưng từ các thành phần hiện có; không phải bộ nguyên tắc đã được khai báo sẵn trong repo.
- **Đề xuất cho Next.js:** cách tổ chức hoặc mở rộng ở project đích, được ghi rõ trong phần tương ứng.

Phạm vi tham chiếu: shell admin, theme, UI primitives, danh sách dữ liệu, form nội dung, cài đặt, dashboard, tìm kiếm toàn cục, phản hồi thao tác, media picker và xác thực. Đây không phải danh mục đầy đủ mọi màn hình/plugin.

Các giá trị pixel quy đổi trong tài liệu giả định `1rem = 16px`, spacing và breakpoint mặc định của Tailwind. Stylesheet admin không định nghĩa lại các thang này. Nếu project đích đã tùy biến chúng, cần đối chiếu cả giá trị thực tế, không chỉ tên class.

## 2. Định hướng thị giác

**Diễn giải thiết kế:** giao diện công cụ quản trị gọn, rõ thứ bậc, có mật độ thông tin vừa phải. Nền xám lạnh, bề mặt card sáng hơn nền, xanh trầm làm điểm nhấn. Dark mode dùng xám than và xanh nhạt.

Những đặc trưng cần giữ:

1. Chia vùng bằng nền và đường viền 1px; card thông thường không có shadow.
2. Màu primary tập trung vào hành động chính, liên kết, trạng thái active và focus.
3. Heading trang nổi bật vừa đủ; heading card nhỏ, đậm, gần với nội dung dữ liệu.
4. Button, input và item điều hướng có độ bo nhỏ; badge trạng thái gọn.
5. Lặp lại cùng cấu trúc tìm kiếm, lọc, bảng, phân trang trên các danh sách.
6. Loading, validation và thông báo là thành phần của luồng thao tác.
7. Màn hình nhỏ xếp nội dung thành một cột và tăng kích thước điều khiển.

Để giữ phong cách này, tránh tự thêm gradient vào shell, blur/glass, shadow lớn cho mọi card, card bo quá tròn, heading quá lớn hoặc mỗi module dùng một bảng màu riêng. Trang xác thực có ảnh nền và gradient phủ ảnh riêng; đó là biến thể ngoài shell admin.

**Lưu ý shadcn/ui:** repo có cấu hình `style: new-york`, `baseColor: zinc`, nhưng bảng màu và component thực tế đã được tùy biến. Chỉ chọn preset tương tự ở project mới chưa đủ để tái hiện giao diện này.

## 3. Màu sắc và theme

### 3.1. Bảng màu mặc định

| Token CSS | Light | Dark | Vai trò |
| --- | --- | --- | --- |
| `--background` | `#f6f7f9` | `#171a1f` | Nền trang |
| `--foreground` | `#20252b` | `#e6ebf1` | Chữ chính |
| `--card` | `#ffffff` | `#20252c` | Card, header, dialog |
| `--sidebar` | `#eef1f5` | `#1b2027` | Nền điều hướng |
| `--primary` | `#214f7d` | `#95bce5` | Nền hành động chính, điểm nhấn |
| `--primary-ink` | `#214f7d` | `#95bce5` | Chữ/icon dùng màu primary |
| `--primary-foreground` | `#ffffff` | `#171a1f` | Chữ trên nền primary |
| `--secondary` | `#e9edf2` | `#2d3540` | Hành động phụ, nền trung tính |
| `--secondary-foreground` | `#354252` | `#d2dae4` | Chữ trên secondary |
| `--accent` | `#e4eaf1` | `#2a3441` | Hover, active navigation |
| `--muted` | `#eef1f4` | `#282f38` | Vùng phụ, table header |
| `--muted-foreground` | `#5d6672` | `#a5afbd` | Mô tả, metadata |
| `--border` | `#d9dfe6` | `#35404e` | Viền card và đường phân chia |
| `--input` | `#7c8998` | `#728297` | Viền control; đậm hơn viền card |
| `--ring` | `#214f7d` | `#95bce5` | Focus |
| `--destructive` | `#b91c1c` | `#fc9999` | Lỗi và nội dung nguy hiểm |

Các utility còn lại được ánh xạ như sau: `card-foreground`, `popover-foreground`, `accent-foreground` và `sidebar-foreground` dùng `--foreground`; `popover` dùng `--card`.

Các phối màu thường gặp:

- Table header: `bg-muted/40`; table row hover: `bg-muted/30`.
- Sidebar item active: `bg-accent text-foreground`, thêm vạch primary.
- Nhãn đếm: `bg-primary/10 text-primary-ink`.
- Media placeholder, notice hoặc khung icon: thường `bg-muted/30`.
- Overlay dialog/mobile navigation: đen ở opacity 45%.

### 3.2. Màu trạng thái

| Badge | Light | Dark |
| --- | --- | --- |
| Trung tính | `bg-secondary text-secondary-foreground border-transparent` | Theo token theme |
| Thành công | `border-emerald-200/70 bg-emerald-50 text-emerald-800` | `border-emerald-800 bg-emerald-950/60 text-emerald-300` |
| Cảnh báo | `border-amber-200/70 bg-amber-50 text-amber-900` | `border-amber-800 bg-amber-950/60 text-amber-200` |
| Nguy hiểm | `border-red-200 bg-red-50 text-destructive` | `border-red-900 bg-red-950/50 text-destructive` |
| Outline | `border-border text-muted-foreground` | Theo token theme |

Button destructive hiện dùng `bg-red-700 text-white hover:bg-red-800`, kể cả trong dark mode. Không thay nền button này bằng `--destructive` một cách máy móc: token dark là màu chữ lỗi sáng.

### 3.3. Theme và tùy chỉnh thương hiệu

**Hiện trạng:**

- Có ba lựa chọn `light`, `dark`, `system`; button chuyển lần lượt Light → Dark → System → Light.
- Dùng class `.dark` ở root; mặc định theo hệ điều hành nếu chưa có lựa chọn khác.
- Lựa chọn đã lưu trong trình duyệt ưu tiên hơn theme mặc định của tài khoản.
- Theo dõi thay đổi theme hệ điều hành khi đang ở System; đồng bộ lựa chọn giữa các tab.
- Logo, chiều cao logo, favicon, font, màu primary, một số màu chữ/liên kết và chiều rộng nội dung có thể được cấu hình lại.
- Tùy chỉnh màu primary tách màu nền `primary` với màu chữ `primary-ink`. Hàm xử lý giữ màu chữ đạt tương phản tối thiểu 4.5 với ba bề mặt đã định trước hoặc dùng màu fallback; đây không phải xác nhận accessibility cho toàn giao diện.
- Một số override như secondary, heading, text và link chỉ áp dụng ở light mode.

Bảng trên phản ánh **giá trị mặc định trong code**, không xác nhận các giá trị đang cấu hình trong database. Font cũng có thể được nạp từ URL cấu hình.

**Đề xuất cho Next.js:** giữ lựa chọn Light/Dark/System; áp dụng theme trước lần vẽ đầu tiên bằng cơ chế phù hợp với project, tránh nháy theme và lỗi hydration. Giữ cơ chế tùy chỉnh brand chỉ khi sản phẩm đích cần.

## 4. Typography, spacing và hình khối

### 4.1. Typography

Font mặc định là system sans:

```css
font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont,
  "Segoe UI", sans-serif;
```

`--admin-font-family` có thể ghi đè font này. Không có cơ sở để coi Inter hay font thương mại nào là font bắt buộc của admin.

| Vai trò | Class điển hình | Giá trị quy đổi |
| --- | --- | --- |
| Tiêu đề trang | `text-2xl font-semibold tracking-tight` | 24px / line-height 32px / 600 |
| Tiêu đề dialog | `text-lg font-semibold tracking-tight` | 18px / 28px / 600 |
| Tiêu đề nhóm cài đặt | `text-base font-semibold` | 16px / 24px / 600 |
| Tiêu đề card | `text-sm font-semibold tracking-tight` | 14px / 20px / 600 |
| Label field | `text-sm font-medium leading-5` | 14px / 20px / 500 |
| Nội dung bảng/menu/button | `text-sm` | 14px / 20px |
| Mô tả trang/card | `text-sm leading-relaxed text-muted-foreground` | 14px / line-height khoảng 22.75px |
| Helper text, lỗi field, badge | `text-xs` | 12px / 16px; helper có thể `leading-relaxed` |
| Chữ input/select/textarea | `text-base sm:text-sm` | 16px dưới 640px; 14px từ 640px |
| Chỉ số dashboard | `text-2xl font-semibold tabular-nums` | 24px / 600; chữ số có độ rộng đều |

Chỉ dùng `truncate` ở nơi chiều rộng thực sự bị giới hạn, như breadcrumb, tên tài khoản và giá trị select. Nội dung form, lỗi và tiêu đề dialog cho phép xuống dòng; các vùng grid thường có `min-w-0` để tránh tràn.

### 4.2. Spacing

Các giá trị được dùng lặp lại, không phải một bộ spacing token riêng do repo khai báo:

| Khoảng | Class | Cách dùng |
| --- | --- | --- |
| 4px | `gap-1`, `space-y-1` | Chi tiết gần nhau |
| 6px | `gap-1.5`, `space-y-1.5` | Tiêu đề/mô tả card, badge icon |
| 8px | `gap-2`, `space-y-2` | Label/control; nhóm button |
| 12px | `gap-3`, `space-y-3` | Toolbar; icon/chữ; nhóm điều khiển |
| 16px | `gap-4`, `p-4` | Toolbar và vùng dữ liệu gọn |
| 20px | `gap-5`, `space-y-5`, `p-5` | Khoảng giữa card, field và padding card |
| 24px | `gap-6`, `space-y-6`, `p-6` | Dialog desktop, nhóm form lớn |
| 32px | `space-y-8` | Nhóm cài đặt, bố cục auth |

### 4.3. Border, radius, shadow và icon

- Border thông thường: 1px, `border-border`; input dùng `border-input`.
- Radius: `rounded-sm` ≈ 4px; `rounded-md` ≈ 6px; `rounded-lg` ≈ 8px. Card/dialog dùng 8px, button/input/badge dùng 6px; avatar/count có thể tròn hoàn toàn.
- Card mặc định không có shadow. Dropdown/select có `shadow-md`; dialog và mobile drawer có `shadow-xl`; toast có `shadow-lg`.
- Icon dùng Lucide. Button/menu thường 16px; sidebar 18px; khung icon cài đặt 20px; metric dashboard 24px; empty state danh sách 32px.
- Icon đi cùng chữ thường cách nhau 8–12px. Icon trang trí dùng `aria-hidden`; nút chỉ có icon cần tên truy cập được.

**Đề xuất cho Next.js:** kiểm tra radius mặc định của shadcn đang có. Nếu card/button bo lớn hơn các thông số trên, chỉnh tại component hoặc theme chung thay vì sửa rải rác từng trang.

## 5. Shell admin và responsive

### 5.1. Bố cục chính

```text
┌───────────────┬─────────────────────────────────────────────┐
│ Logo / brand  │ Header: breadcrumb   search/theme/user      │
│               ├─────────────────────────────────────────────┤
│ Sidebar       │ H1 + mô tả                   Page actions   │
│ nhóm menu     │                                             │
│               │ Nội dung: bảng / card / form / dashboard    │
│               │                                             │
└───────────────┴─────────────────────────────────────────────┘
```

| Thành phần | Hiện trạng |
| --- | --- |
| Sidebar desktop | Fixed, toàn chiều cao viewport, rộng 256px (`w-64`) |
| Sidebar thu gọn | Rộng 72px (`w-18`), icon 18px trong nút 44px |
| Ngưỡng sidebar desktop | Từ `md` = 768px |
| Header | Sticky top, cao 64px (`h-16`), `border-b bg-card` |
| Offset nội dung | Từ 768px, cách đầu inline bằng chiều rộng sidebar hiện tại |
| Main mặc định | Căn giữa vùng sau sidebar, `max-w-[1440px]` |
| Padding main | 16px; từ 640px: ngang 24px/dọc 20px; từ 1024px: ngang 32px |
| Padding header | Ngang 16px → 24px ở `sm` → 32px ở `lg` |
| Heading row | `mb-5 flex flex-wrap items-start justify-between gap-4` |
| Nhóm page actions | `flex shrink-0 items-center gap-2` |
| Mô tả dưới H1 | `mt-1.5 max-w-2xl text-sm leading-relaxed text-muted-foreground` |

Tùy chọn container hiện có: `container-xl` = 1320px; `container-3xl` = 1320px và tăng lên 1600px khi viewport từ 1920px; `container-fluid` bỏ max-width. Các max-width này áp dụng cho main, không bao gồm sidebar.

Main cuộn theo trang; sidebar có vùng cuộn riêng với scrollbar mảnh và `overscroll-contain`. Header không nằm trong main có max-width: header trải hết vùng sau sidebar.

### 5.2. Điều hướng

- Item thường: `min-h-11 sm:min-h-9 rounded-md px-3 py-2 text-sm`, icon/chữ cách 12px.
- Item thường dùng chữ muted; hover chuyển sang `bg-accent text-foreground`.
- Item active dùng `bg-accent font-medium text-foreground`, vạch primary rộng 2px ở cạnh đầu và `aria-current="page"`.
- Menu con thụt 20px, đường viền dọc và padding đầu 12px (`ms-5 border-s ps-3`).
- Nhóm chứa trang active tự mở. Trạng thái đóng/mở nhóm và thu gọn sidebar được lưu ở trình duyệt.
- Vị trí cuộn sidebar được giữ trong session; trạng thái compact/expanded có vị trí riêng.
- Ở chế độ compact, nhóm có menu con mở dropdown; mỗi icon có nhãn/title.
- Breadcrumb desktop bắt đầu bằng Dashboard và nối đến tiêu đề hiện tại. Code gốc không dựng breadcrumb nhiều cấp cho mọi tài nguyên.

### 5.3. Màn hình nhỏ

| Ngưỡng | Thay đổi đáng chú ý |
| --- | --- |
| Dưới 400px | Header giảm padding ngang còn 8px và giảm gap |
| Dưới 640px | Control thường cao 44px; input/select dùng chữ 16px |
| Từ 640px (`sm`) | Control gọn hơn; settings item có thể thành hai cột |
| Dưới 768px | Sidebar desktop ẩn; breadcrumb ẩn; mở menu bằng nút hamburger |
| Từ 768px (`md`) | Sidebar fixed và main có offset tương ứng |
| Từ 1024px (`lg`) | Form chính/phụ và phần cài đặt chuyển sang hai cột |
| Dưới 1280px | Nhiều button trên header dùng kích thước 44px và ẩn nhãn/shortcut phụ |
| Từ 1280px (`xl`) | Widget dashboard có thể chia hai cột; tên tài khoản hiện |

Mobile navigation là dialog dạng drawer: rộng `min(320px, 100vw - 48px)`, overlay đen 45%, có nút đóng và trả focus khi đóng. Chọn một đường dẫn sẽ đóng drawer. Khi chuyển sang desktop, drawer được đóng.

**Đề xuất cho Next.js:** giữ trang không tràn ngang; chỉ bảng hoặc vùng nội dung thực sự cần mới cuộn ngang. Kiểm tra riêng 375px, 640px, 768px, 1024px và 1440px; kiểm tra thêm 1920px nếu dùng container mở rộng.

## 6. Đặc tả component

### 6.1. Button

Base: `inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md border text-sm font-medium`. Transition chỉ cho màu nền/chữ/viền/shadow. Icon mặc định 16px.

| Variant | Class màu chính | Vai trò |
| --- | --- | --- |
| Default | `bg-primary text-primary-foreground hover:bg-primary/90` | Tạo, lưu, tiếp tục |
| Secondary | `bg-secondary text-secondary-foreground hover:bg-secondary/75` | Hành động phụ |
| Outline | `border-input bg-card hover:bg-accent hover:text-accent-foreground` | Quay lại, hủy, tìm kiếm, lưu và tiếp tục |
| Ghost | `hover:bg-accent hover:text-accent-foreground` | Icon toolbar, thao tác nhẹ |
| Destructive | `bg-red-700 text-white hover:bg-red-800` | Xác nhận xóa |
| Link | `text-primary-ink underline-offset-4 hover:underline` | Hành động kiểu liên kết |

| Size | Dưới 640px | Từ 640px | Padding ngang |
| --- | --- | --- | --- |
| Default | 44px | 36px | 16px |
| Small | 44px | 32px | 12px; chữ 12px |
| Large | 48px | 40px | 24px |
| Icon | 44 × 44px | 36 × 36px | Theo kích thước |

State: disabled opacity 50%, chặn tương tác. Loading giữ nhãn, thêm spinner, khóa thao tác và đặt `aria-busy`. Focus-visible dùng ring 3px ở opacity 25% và viền ring. Component gốc mặc định `type="button"`; nút submit phải khai báo rõ.

**Diễn giải thiết kế:** trong một nhóm hành động nên có một hành động chính rõ nhất; hành động còn lại dùng outline/ghost tùy vai trò.

### 6.2. Input, textarea và field

- Input/select: `h-11 sm:h-9 px-3`, nền card, border input, radius 6px.
- Textarea: `min-h-28` (112px), `resize-y px-3 py-2.5`, line-height relaxed.
- Placeholder: `text-muted-foreground/80`.
- Focus: `focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/20`.
- Invalid: `aria-invalid:border-destructive aria-invalid:focus-visible:ring-destructive/20`.
- Disabled: `disabled:cursor-not-allowed disabled:bg-muted disabled:opacity-60`.
- Readonly: `bg-muted/50`.
- Field dùng `space-y-2`: label bên trên, control, mô tả nếu có, lỗi nếu có.
- Label có liên kết với control bằng ID; dấu `*` đỏ chỉ là biểu thị thị giác, control vẫn cần `required` khi áp dụng.
- Helper 12px muted; lỗi 12px medium destructive và `role="alert"`. Control nối tới helper/lỗi bằng `aria-describedby`, nhận `aria-invalid` khi lỗi.
- Nhóm field liên quan có thể dùng `fieldset`/`legend`.

Search input dùng icon đầu 16px, `ps-9`; khi có thao tác clear, chừa `pe-11`. Clear trả focus vào input. Search danh sách và search toàn cục có cơ chế gửi khác nhau, xem mục 7 và 8.

### 6.3. Select, checkbox và radio

- Single select dùng Radix Select và cùng style với input. Menu rộng ít nhất bằng trigger, cách trigger 4px, có border và shadow vừa.
- Select chuyển sang dialog tìm kiếm khi `searchable`, khi multiple, hoặc tự động nếu có từ 10 option và chưa tắt tìm kiếm.
- Dialog tìm kiếm cho phép chọn một/nhiều; chọn đơn đóng sau khi chọn; chọn nhiều có số lượng đã chọn và nút Done.
- Vẫn có NativeSelect riêng cho những nơi cần listbox native; không áp một menu custom cho tất cả trường hợp.
- Checkbox/radio vẽ hình 18px; wrapper và pseudo-element mở vùng tương tác lên 44px trên mobile, 28px từ `sm`.
- Checkbox hỗ trợ indeterminate để biểu diễn chọn một phần danh sách. Checked dùng primary; radio có chấm tròn bên trong.

**Đề xuất cho Next.js:** dùng Select cho tập option ngắn; với tập dài, dựng mẫu tìm kiếm tương đương bằng component phù hợp đang có. Giữ nguyên nhãn, chọn bằng bàn phím và quản lý focus khi thay primitive.

### 6.4. Card và FormSection

| Phần | Class tham chiếu |
| --- | --- |
| Card | `min-w-0 rounded-lg border bg-card text-card-foreground` |
| Header | `space-y-1.5 border-b px-5 py-3` |
| Title | `text-sm font-semibold tracking-tight` |
| Description | `text-sm leading-relaxed text-muted-foreground` |
| Content | `p-5` |
| Footer | `flex items-center gap-3 border-t px-5 py-3` |
| FormSection content | `space-y-5` trong CardContent |

Một số màn dùng card `p-5` với divider bên trong thay cho CardHeader riêng. Giữ cùng padding và thứ bậc chữ khi áp dụng biến thể này. FormSection gắn tiêu đề với section bằng `aria-labelledby`.

### 6.5. Bảng dữ liệu

- Table `w-full text-sm`, nằm trong vùng cuộn ngang cục bộ khi cần.
- Header `bg-muted/40 text-xs text-muted-foreground`; `th` font medium.
- Cell `px-4 py-3 align-middle` (ngang 16px, dọc 12px).
- Row `border-b last:border-0 hover:bg-muted/30`.
- Checkbox đầu hàng dùng vùng chạm riêng; trạng thái chọn một phần biểu diễn bằng indeterminate.
- Header sortable là button, có hướng ↑/↓ ở cột đang sort và `aria-sort` phù hợp.
- Tên tài nguyên là điểm truy cập nội dung; metadata/ngày tháng dùng muted; ID dùng `tabular-nums`.
- Trạng thái published được minh họa bằng badge success; trạng thái khác có thể outline. Ánh xạ trạng thái phải theo nghiệp vụ project mới.
- Action ít dùng như sửa/xóa đặt trong dropdown dấu ba chấm; tên truy cập của dropdown phải rõ.

### 6.6. Dialog và dropdown

| Thông số dialog | Hiện trạng |
| --- | --- |
| Chiều rộng | `calc(100% - 2rem)`, chừa 16px mỗi cạnh |
| Size `sm` / `lg` / `xl` / `full` | Max 448 / 768 / 1024 / 1600px |
| Size mặc định | `lg` |
| Chiều cao | Max `calc(100dvh - 2rem)` |
| Bề mặt | `rounded-lg border bg-card shadow-xl` |
| Header | `p-5`, từ `sm` dùng `px-6 py-4`; border dưới |
| Body | `p-5 sm:p-6`, cuộn dọc riêng, `min-h-0 overscroll-contain` |
| Footer | Border trên, `px-5 py-3 sm:px-6`, wrap, căn hành động cuối hàng |

Dialog có title, description khi cần, nút X, quản lý focus đầu vào và trả focus về phần tử mở dialog. Focus đầu tiên có thể được chỉ định; nếu không, component tìm field nhập thích hợp. Các dialog lồng nhau được tăng layer động.

Dropdown: `min-w-48` (192px), `rounded-lg border bg-popover p-1.5 shadow-md`, cách trigger 6px, tránh biên viewport 8px. Item `min-h-11 sm:min-h-9 rounded-md px-3 py-2 text-sm`, hover/highlight accent. Item destructive dùng chữ đỏ và nền đỏ nhẹ khi highlight.

Không chuyển dialog nội dung dài thành một trang cuộn cả header/footer ra ngoài viewport; body mới là vùng cần cuộn.

## 7. Mẫu trang và luồng thao tác

### 7.1. Trang danh sách

```text
H1: Tên tài nguyên                                [+ Tạo mới]
┌──────────────────────────────────────────────────────────┐
│ [Thanh bulk khi có lựa chọn]                              │
├──────────────────────────────────────────────────────────┤
│ Search     Status     [Tìm] [Lọc nâng cao]   Table tools    │
├──────────────────────────────────────────────────────────┤
│ Checkbox │ Tên │ Trạng thái │ Ngày tạo │ ...              │
│ ...                                                      │
├──────────────────────────────────────────────────────────┤
│ Tổng kết quả / Số hàng             [Trước] 1/N [Sau]       │
└──────────────────────────────────────────────────────────┘
```

**Hiện trạng:**

1. Hành động tạo nằm ở page actions cạnh tiêu đề, theo quyền của người dùng.
2. Thanh bulk chỉ xuất hiện khi có dòng được chọn và tài nguyên hỗ trợ.
3. Toolbar `flex flex-wrap items-center gap-3 border-b p-4`. Search co giãn, min-width 192px; lọc status min-width 144px. Table tools căn về cuối hàng khi đủ chỗ.
4. Search danh sách áp dụng bằng submit/Enter hoặc nút Search; đổi status áp dụng ngay. Clear ở input chỉ xóa chữ trong ô; nút reset bộ lọc mới xóa các điều kiện đã áp dụng.
5. Đổi search/filter/sort/page size đưa danh sách về trang 1. Các điều kiện áp dụng được mang trong URL; giữ vị trí cuộn khi điều hướng bảng.
6. Đang tải: toolbar có spinner trong một vùng dành sẵn, khóa điều khiển liên quan, card đặt `aria-busy`.
7. Chọn tất cả chọn các dòng của trang hiện tại. Thay đổi dữ liệu/trang/bộ lọc xóa lựa chọn để tránh thao tác nhầm.
8. Xóa một hoặc nhiều mục đều mở dialog xác nhận; nút xác nhận dùng destructive, có loading và ngăn đóng khi đang xử lý trong luồng này.
9. Empty state vẫn nằm trong card: icon Files 32px, chữ “No results found”, `p-12`, căn giữa.
10. Footer `px-4 py-3`, wrap khi hẹp: tổng kết quả, số hàng/trang, Previous/Next và số trang. Mặc định 20 hàng; lựa chọn chuẩn 10/20/50/100, có thể bổ sung giá trị hiện tại hợp lệ.

### 7.2. Form nội dung chung

- Grid: `grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_300px]`.
- Cột chính: các nhóm nội dung, `min-w-0 space-y-5`.
- Cột phụ: status, ảnh, phân loại và nhóm lưu tùy schema.
- Card hành động lưu có thể `lg:sticky lg:top-24` (96px).
- Có Save và Save and continue; chỉ nút đang thực hiện hiển thị loading, cả nhóm bị khóa khi submit.
- Có trạng thái “Unsaved changes”, lỗi ngay tại field và phần tổng hợp lỗi trong card hành động khi có lỗi.
- Quay lại ở page actions, variant outline.
- Có cảnh báo rời trang khi dữ liệu thay đổi chưa lưu; baseline dirty cập nhật sau khi lưu thành công.

**Đề xuất cho Next.js:** diễn đạt rõ Save & return và Save & continue editing nếu hai nút có đích điều hướng khác nhau. Tái hiện cảnh báo rời trang bằng cơ chế của router/form đích; `beforeunload` chỉ xử lý phần rời trang/tải lại, không thay thế toàn bộ điều hướng trong app.

### 7.3. Biến thể màn sửa Page

Đây là biến thể riêng trong code, không phải quy tắc đã có ở mọi form:

- Grid `lg:grid-cols-[minmax(0,1fr)_19rem]`: cột phụ 304px; gap 20px, từ `lg` tăng 24px.
- Sidebar form `lg:sticky lg:top-20`, max-height `calc(100dvh - 6rem)`, có cuộn nội bộ.
- Card Publish gom status và hai nút Save & return / Save & continue editing, nút rộng toàn cột.
- Dưới `lg`, có thanh lưu fixed phía dưới, nền card, border trên, z-index 20. Từ `md`, thanh bắt đầu sau sidebar.
- Thanh lưu chừa safe area đáy: `pb-[max(0.75rem,env(safe-area-inset-bottom))]`.
- Form chừa `pb-20` trên màn nhỏ để nội dung không bị thanh lưu che.
- Lỗi tổng hợp hiển thị trong khung đỏ nhẹ bên cạnh các lỗi field.

**Đề xuất cho Next.js:** chọn biến thể này cho form dài cần thao tác lưu luôn dễ tiếp cận; không mặc định mọi trang đều cần bottom bar.

### 7.4. Cài đặt

Trang tổng quan cài đặt:

- Search theo tên và mô tả cài đặt, lọc ngay trong danh sách hiện có.
- Mỗi nhóm: mô tả bên trái 240px (`15rem`), nội dung bên phải; tách hai cột từ `lg`.
- Nhóm cách nhau 32px. Card item chia hai cột từ `sm`.
- Item `p-5`, icon trong khung 40px có viền/nền muted nhẹ, title 14px semibold, mô tả 12px muted.
- Toàn item là liên kết; icon mũi tên hiện khi hover hoặc focus.

Trang form cài đặt:

- Grid `lg:grid-cols-[16rem_minmax(0,1fr)]`, gap 20px; cột mô tả/navigation 256px sticky top 80px.
- Content xếp card theo nhóm; các phần có divider và khoảng cách 20–32px.
- Footer card có Save settings, loading và nhãn thay đổi chưa lưu; hành động phụ ở cuối hàng.
- Navigation phụ xếp hàng và wrap trên màn nhỏ, thành cột trên desktop; active dùng secondary.

### 7.5. Dashboard

- Metric grid `gap-4`, một cột → hai cột từ `sm` → bốn cột từ `lg`.
- Metric card có icon 24px trong nền màu nhẹ, label muted 14px, số 24px semibold/tabular-nums.
- Widget grid `gap-5 xl:grid-cols-2`; một số widget chiếm toàn hàng.
- Widget có header tiêu đề và nhóm công cụ; nội dung có trạng thái loading, lỗi/thử lại, rỗng hoặc dữ liệu.
- Loading widget dùng vùng có `min-h-48` (192px) để giữ không gian.
- Có quản lý hiển thị/thứ tự widget và điều khiển sắp xếp ngoài kéo thả.
- Biểu đồ analytics đã khảo sát dùng màu primary, grid nhẹ, vùng tô opacity thấp và phân biệt đường liền/đứt; đi kèm dữ liệu bảng. Đây là tham chiếu phong cách, không yêu cầu dùng lại bộ vẽ SVG hiện tại.

### 7.6. Xác thực

- Trang riêng không có sidebar/header admin; nền card, cao tối thiểu viewport động.
- Form max-width 384px, căn giữa trong cột, khoảng nhóm 32px, field cách 20px.
- Cột form có border primary 4px ở cạnh trên, title 24px, mô tả 14px.
- Khi có ảnh nền: từ `lg` chia hai cột; từ `xl` cột ảnh rộng hơn, theo grid `minmax(24rem,1fr) minmax(0,2fr)`.
- Ảnh chỉ hiện từ `lg`, cover/center, có lớp gradient tối và tên thương hiệu ở đáy.
- Submit rộng toàn form, có loading; có nhãn field, remember checkbox và liên kết quên mật khẩu.

## 8. Phản hồi, tìm kiếm và trạng thái

### 8.1. Toast

- Góc dưới bên phải, cách biên 16px; rộng `calc(100% - 2rem)`, tối đa 384px.
- Tối đa bốn toast; các toast cùng nội dung và severity được gộp.
- Mỗi toast `rounded-md border border-l-4 bg-card p-4 shadow-lg`, icon 20px, tiêu đề và nội dung 14px.
- Viền trái: success dùng primary, error dùng destructive, warning amber-500, info sky-500. Success ở toast không dùng cùng màu emerald như badge success.
- Tự đóng sau khoảng 5 giây; khi hover/focus thì dừng, khi rời thì bộ đếm 5 giây bắt đầu lại.
- Có nút đóng. Error/warning dùng `role="alert"`; success/info dùng `role="status"`.

### 8.2. Tìm kiếm toàn cục

- Mở từ header hoặc shortcut Ctrl/Cmd + K; cũng hỗ trợ Ctrl/Cmd + `/` và `/` khi target là body.
- Mở dialog, focus vào input; gõ tìm kiếm debounce 200ms, hủy request cũ khi query đổi.
- Dùng phím mũi tên để chọn, Enter để điều hướng, Escape để đóng theo dialog.
- Có loading, kết quả, không có kết quả và lỗi/thử lại; danh sách kết quả cuộn riêng.
- Tên nhóm/metadata giúp phân biệt các kết quả cùng tên; không chỉ trả về một danh sách liên kết thiếu ngữ cảnh.

### 8.3. Bảng trạng thái cần giữ khi triển khai

| Tình huống | Mẫu phản hồi |
| --- | --- |
| Đang gửi form | Spinner trong button đang submit, giữ nhãn, khóa gửi lặp |
| Đang đổi bảng | Spinner ở toolbar, `aria-busy`, khóa control liên quan |
| Đang tải widget | Giữ vùng nội dung, spinner có nhãn truy cập |
| Không có dữ liệu | Thông điệp trong vùng nội dung tương ứng, không coi là lỗi |
| Sai field | Viền lỗi + chữ lỗi ngay dưới field; form có thể có tổng hợp lỗi |
| Lỗi request | Thông báo rõ lỗi; có thể thử lại tại vùng liên quan |
| Thành công | Toast mô tả kết quả; cập nhật trạng thái dirty sau khi lưu |
| Sắp xóa | Dialog nêu đối tượng/phạm vi; Cancel outline, Delete destructive |
| Có thay đổi chưa lưu | Nhãn dirty và cảnh báo trước khi rời form |

**Đề xuất cho Next.js:** phân biệt “chưa có bản ghi” với “không có kết quả theo bộ lọc”; cung cấp hành động tạo mới/xóa bộ lọc khi phù hợp. Đây là cải tiến có thể thêm; empty state danh sách chung hiện chỉ có icon và thông báo.

### 8.4. Media và nội dung dài

- Media picker có vùng preview nền muted nhẹ, border/radius 6px, nút chọn/upload và thao tác xóa ảnh.
- Có nhiều tỷ lệ theo ngữ cảnh: 4:3, vuông, banner 5:1; ảnh featured dùng 16:9 và giới hạn cao 144px.
- Ảnh preview dùng `object-contain` để nhìn toàn bộ ảnh. Khi có upload URL, vùng preview hỗ trợ drop file; vẫn có nút thao tác.
- Editor nội dung có khung nhập tối thiểu 352px (`22rem`), padding 16px; bảng dài cuộn ngang trong vùng riêng.
- Heading trong rich text có thang riêng, ví dụ h1 32px, h2 24px, h3 20px. Không áp thang heading nội dung này cho title của card hoặc toàn bộ shell admin.

## 9. Accessibility và chuyển động

**Hiện trạng trong code đã đọc:**

- Có skip link tới main, main có ID và có thể nhận focus.
- Landmark và tên vùng: nav quản trị, breadcrumb, pagination, dialog, fieldset khi phù hợp.
- Có ring focus-visible cho link/button/control/menu; icon-only button có aria-label trong các mẫu chính.
- Item điều hướng có `aria-current`; header bảng dùng `scope`; sort dùng `aria-sort`.
- Label nối với field; lỗi/helper nối bằng ARIA; loading có trạng thái đọc được.
- Dialog/drawer dùng Radix, có xử lý focus khi mở/đóng; generic confirm đặt focus đầu tiên vào Cancel.
- Motion chủ yếu là transition màu/focus và xoay chevron/spinner. Không có một duration riêng thống nhất do admin khai báo.
- Với `prefers-reduced-motion: reduce`, animation/transition bị rút về 0.01ms, animation một lần, scroll về auto; spinner cũng có `motion-reduce:animate-none`.
- Có hỗ trợ RTL bằng `dir`, logical spacing và các chỉnh sửa sidebar/chevron; mức độ đầy đủ cần kiểm thử khi sản phẩm đích dùng RTL.

**Đề xuất cho Next.js:** kiểm thử bàn phím, focus, tương phản và screen reader ở project đích. Không xem việc dùng Radix/shadcn hoặc sao chép token là bằng chứng toàn bộ sản phẩm đã đạt chuẩn accessibility.

Giữ kích thước control mobile 44px làm mặc định tham chiếu; code hiện có vài ngoại lệ như nút đóng toast/ảnh nhỏ hơn, vì vậy không coi mọi target hiện tại đều đạt 44px. Khi dựng mới, có thể mở vùng chạm mà giữ icon nhỏ.

## 10. Áp dụng vào Next.js + Tailwind + shadcn/ui

Phần này là **đề xuất triển khai cho project đích**, không phải cấu trúc bắt buộc của Kern CMS. Không cần đổi framework hoặc tái tạo nghiệp vụ CMS.

### 10.1. CSS nền tảng cho Tailwind v4

Repo nguồn dùng Tailwind v4. Đoạn dưới tái hiện màu mặc định và mapping utility của admin; phù hợp khi project đích cũng dùng v4. Nếu dùng v3, giữ các biến màu rồi ánh xạ tương đương trong cấu hình theme hiện có; không dán cú pháp `@theme` của v4 sang v3.

Gộp vào CSS global hiện có. Giữ các import/plugin/token khác mà sản phẩm đang cần. Không ghi đè cả stylesheet shadcn một cách máy móc; kiểm tra các mapping màu, font và radius đang có để tránh định nghĩa xung đột.

```css
@import "tailwindcss"; /* Chỉ giữ một import Tailwind trong file thực tế. */
@custom-variant dark (&:where(.dark, .dark *));

:root {
  color-scheme: light;
  --background: #f6f7f9;
  --foreground: #20252b;
  --card: #ffffff;
  --sidebar: #eef1f5;
  --primary: #214f7d;
  --primary-ink: #214f7d;
  --primary-foreground: #ffffff;
  --secondary: #e9edf2;
  --secondary-foreground: #354252;
  --accent: #e4eaf1;
  --muted: #eef1f4;
  --muted-foreground: #5d6672;
  --border: #d9dfe6;
  --input: #7c8998;
  --ring: #214f7d;
  --destructive: #b91c1c;
}

.dark {
  color-scheme: dark;
  --background: #171a1f;
  --foreground: #e6ebf1;
  --card: #20252c;
  --sidebar: #1b2027;
  --primary: #95bce5;
  --primary-ink: #95bce5;
  --primary-foreground: #171a1f;
  --secondary: #2d3540;
  --secondary-foreground: #d2dae4;
  --accent: #2a3441;
  --muted: #282f38;
  --muted-foreground: #a5afbd;
  --border: #35404e;
  --input: #728297;
  --ring: #95bce5;
  --destructive: #fc9999;
}

@theme inline {
  --font-sans: var(--admin-font-family, ui-sans-serif, system-ui,
    -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif);
  --color-background: var(--background);
  --color-foreground: var(--foreground);
  --color-card: var(--card);
  --color-card-foreground: var(--foreground);
  --color-popover: var(--card);
  --color-popover-foreground: var(--foreground);
  --color-sidebar: var(--sidebar);
  --color-sidebar-foreground: var(--foreground);
  --color-primary: var(--primary);
  --color-primary-ink: var(--primary-ink);
  --color-primary-foreground: var(--primary-foreground);
  --color-secondary: var(--secondary);
  --color-secondary-foreground: var(--secondary-foreground);
  --color-accent: var(--accent);
  --color-accent-foreground: var(--foreground);
  --color-muted: var(--muted);
  --color-muted-foreground: var(--muted-foreground);
  --color-border: var(--border);
  --color-input: var(--input);
  --color-ring: var(--ring);
  --color-destructive: var(--destructive);

  /* Cố định radius cho project đích, theo kích thước dùng ở nguồn. */
  --radius-sm: 0.25rem;
  --radius-md: 0.375rem;
  --radius-lg: 0.5rem;
}

@layer base {
  * { @apply border-border; }
  body { @apply bg-background font-sans text-foreground antialiased; }
  ::selection { @apply bg-primary/15 text-foreground; }
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
    scroll-behavior: auto !important;
  }
}
```

Các semantic token đã đủ cho component trong phạm vi khảo sát; token mở rộng như chart hoặc sidebar riêng của shadcn có thể cần thêm tùy component project đích. Map chúng theo vai trò và kiểm tra cả hai theme. CSS trên chưa triển khai theme persistence, focus cho link, cursor hay các state của component: áp dụng tiếp các đặc tả mục 6 và 9.

Nếu shadcn hiện có map màu kiểu `hsl(var(--primary))`, không đưa giá trị HEX vào mapping đó; đổi mapping sang `var(--primary)` hoặc chuyển toàn bộ định dạng màu nhất quán. `primary-ink` là token tùy chỉnh cần thêm, không giả định có sẵn ở shadcn.

### 10.2. Công thức class tham chiếu

```text
Main:
  mx-auto max-w-[1440px] p-4 sm:px-6 sm:py-5 lg:px-8

Page header:
  mb-5 flex flex-wrap items-start justify-between gap-4

Page title:
  text-2xl font-semibold tracking-tight

Card:
  min-w-0 rounded-lg border bg-card text-card-foreground

Form content stack:
  space-y-5

Form grid, biến thể chung:
  grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_300px]

List toolbar:
  flex flex-wrap items-center gap-3 border-b p-4

Input:
  h-11 w-full min-w-0 rounded-md border border-input bg-card
  px-3 py-2 text-base text-foreground sm:h-9 sm:text-sm
  placeholder:text-muted-foreground/80 outline-none
  transition-[border-color,box-shadow]
  focus-visible:border-ring focus-visible:ring-[3px]
  focus-visible:ring-ring/20
  disabled:cursor-not-allowed disabled:bg-muted disabled:opacity-60
  [&[readonly]]:bg-muted/50
  aria-invalid:border-destructive
  aria-invalid:focus-visible:ring-destructive/20
```

Đây là công thức style, không phải component hoàn chỉnh. Ghép với primitive shadcn/Radix, label/ARIA, state và xử lý dữ liệu của app mới.

### 10.3. Ranh giới trách nhiệm

| Kế thừa từ thiết kế | Project Next.js tự cung cấp |
| --- | --- |
| Token, spacing, typography, radius | Route, backend và dữ liệu nghiệp vụ |
| Shell/sidebar/header và active style | Tính active từ route của project đích |
| Mẫu list/filter/pagination | URL query và logic fetch/phân trang |
| Field, lỗi, loading và dirty indicator | Validation, submit, quản lý form |
| Dialog xác nhận, toast | Thực hiện mutation và nội dung thông báo |
| Theme Light/Dark/System | Cơ chế lưu/khởi tạo theme phù hợp với SSR |
| Nhóm menu và trạng thái theo quyền | Mô hình quyền và kiểm tra truy cập ở backend |

Gợi ý cấu trúc: một `AdminShell`, `PageHeader`, `FormSection`, `DataTableToolbar`, `TablePagination` và các UI primitives dùng chung. Menu nhận cấu hình của sản phẩm; active state, dữ liệu, router và session được nối bằng lớp ứng dụng của Next.js. Các component tương tác có client boundary phù hợp; không cần biến toàn bộ app thành client component chỉ để giống giao diện.

Các key localStorage, endpoint, alias `@admin`, Inertia props, page registry và lớp session của Kern là chi tiết ứng dụng nguồn. Project đích đặt tên/cơ chế tương ứng của mình.

### 10.4. Thứ tự triển khai và kiểm tra

1. Cài token light/dark và kiểm tra font/radius/breakpoint của project đích.
2. Tùy biến Button, Input, Select, Card, Badge, Dialog, DropdownMenu và Table theo thông số trên.
3. Dựng shell: sidebar 256/72px, header 64px, main và page header.
4. Dựng một trang danh sách đủ filter/sort/pagination/empty/loading/delete confirm.
5. Dựng một form đủ lỗi/dirty/loading/save; chọn cột phụ 300px hoặc biến thể Page một cách có chủ đích.
6. Đối chiếu mẫu ở light/dark và các viewport mục 5; thống nhất trước khi nhân rộng.

Checklist nghiệm thu đề xuất:

- [ ] Nền, surface, border input và border card phân biệt đúng ở cả hai theme.
- [ ] Primary, chữ trên primary và primary-ink có vai trò đúng; destructive button vẫn đọc rõ.
- [ ] H1 24px; title card 14px; field/table không bị phóng to theo heading nội dung rich text.
- [ ] Shell đúng kích thước; thu gọn sidebar cập nhật cả main và thanh lưu nếu có.
- [ ] Mobile drawer mở/đóng và trả focus; header không tràn ở 375px.
- [ ] Toolbar wrap; bảng dài cuộn cục bộ; mô tả/lỗi dài không làm vỡ grid.
- [ ] Field có label, required, helper/error; Tab và Enter hoạt động đúng.
- [ ] Loading ngăn gửi lặp, giữ ngữ cảnh; không mất dữ liệu khi validation thất bại.
- [ ] Sort/filter/page size có trạng thái rõ và URL tương ứng; chọn tất cả có phạm vi dễ hiểu.
- [ ] Dialog nằm trong viewport, focus đúng; menu/select trong dialog không bị che.
- [ ] Thanh lưu mobile chừa safe area và không che field cuối.
- [ ] Có phản hồi thành công/lỗi, cảnh báo dữ liệu chưa lưu và xác nhận trước khi xóa.
- [ ] Reduced motion, theme System và tải lại trang hoạt động đúng.

Đề xuất quản lý z-index ở một nơi. Tham chiếu nguồn: header/bottom bar 20, sidebar 30, mobile overlay/drawer 40/50, dialog đầu tiên overlay/content 50/51 rồi tăng khi lồng, dropdown 70, select 130, toast 200, skip link 80. Đây là giá trị hiện có, chưa phải thang layer đã chuẩn hóa; kiểm tra tổ hợp overlay thực tế trước khi chốt ở app mới.

### 10.5. Prompt giao việc cho AI trong project đích

> Đọc design-system.md và dùng làm tài liệu tham chiếu để tái hiện phong cách Kern Admin trong project hiện tại. Giữ Next.js + Tailwind CSS + shadcn/ui và kiến trúc nghiệp vụ đang có. Ưu tiên token, shell, typography, kích thước control, card, bảng và state tương tác được mô tả. Phân biệt hiện trạng với đề xuất; không tự thêm hiệu ứng hoặc đổi phong cách. Kiểm tra phiên bản Tailwind, mapping màu và radius hiện tại trước khi áp dụng. Bắt đầu bằng một trang danh sách và một form có loading, validation, empty state và xác nhận xóa, rồi đối chiếu responsive/light/dark trước khi mở rộng. Không cần truy cập repository Kern nếu thông số trong tài liệu đã đủ; hỏi rõ khi thiếu thông tin nghiệp vụ.

## 11. Nguồn khảo sát và khả năng truy vết

Các đường dẫn sau thuộc **repo Kern CMS gốc**, chỉ để kiểm tra nguồn nếu có repo; project Next.js không cần có các file này. Dùng `base` dưới đây để chỉ `platform/core/base/resources/js/admin`.

| Phạm vi | File nguồn |
| --- | --- |
| Stack và preset | `package.json`, `components.json` |
| Màu, font, container, reduced motion, rich text | `platform/core/base/resources/css/admin.css` |
| Shell, header, page heading, compact navigation | `base/layouts/admin-layout.tsx` |
| Theme và appearance override | `base/lib/theme.ts`, `base/lib/appearance.ts`, `base/components/admin-appearance.tsx`, `base/components/theme-switch.tsx` |
| Navigation, drawer, vùng cuộn | `base/components/ui/sidebar.tsx` |
| Component nền tảng | `base/components/ui/{button,input,card,badge,table,select,checkbox,radio,dialog,dropdown-menu}.tsx` |
| Field và nhóm form | `base/components/field.tsx`, `base/components/form-section.tsx` |
| Danh sách dữ liệu | `base/components/content-resource-index.tsx`, `base/components/data-table-toolbar.tsx`, `base/components/filter-toolbar.tsx`, `base/components/search-input.tsx`, `base/components/table-pagination.tsx` |
| Query và page size | `base/lib/table-query.ts`, `base/lib/pagination.ts` |
| Form chung và dirty guard | `base/components/content-resource-form.tsx`, `base/lib/use-unsaved-changes.ts` |
| Form Page riêng | `platform/packages/page/resources/js/admin/Form.tsx` |
| Toast, confirm, loading, global search | `base/components/{toast,confirm-dialog,loading-indicator,global-search}.tsx` |
| Media và upload | `base/components/media-picker.tsx`, `base/components/file-upload.tsx` |
| Cài đặt | `platform/core/setting/resources/js/admin/Settings/{Index,Form,settings-section,section-nav}.tsx` |
| Dashboard | `platform/core/dashboard/resources/js/admin/Dashboard/{Index,analytics-visuals}.tsx`, `platform/core/dashboard/resources/js/admin/components/{widget-panel,widget-loading}.tsx` |
| Xác thực | `platform/core/acl/resources/js/admin/Auth/Form.tsx` |

Khi giao diện admin gốc thay đổi, cập nhật thông số và mốc khảo sát của tài liệu này trước khi dùng làm chuẩn mới. Các đề xuất cho Next.js cần được kiểm chứng trên app đích, đặc biệt ở SSR/theme, component đang tùy biến và accessibility.
