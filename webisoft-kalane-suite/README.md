# Webisoft Kalane Suite (کالانه)

افزونه‌ی وردپرس برای فروشگاه‌های **ووکامرس + المنتور (قالب Woodmart)**؛ جایگزین کامل اسنیپت‌های Code Snippets پروژه‌ی کالانه، با همان معماری افزونه‌ی Webisoft Finance Suite (آلیس‌یار): اسکیمای مرکزی تنظیمات ← پنل ادمین خودکار ← شورت‌کدها.

## شورت‌کدها
`[kalane_stories] [kalane_banner] [kalane_categories] [kalane_divider text=""] [kalane_product_slider type="best_sellers" limit="10" category="slug"] [kalane_blog_slider limit="10" category="slug"] [kalane_custom_widget]`

`type` اسلایدر محصول: `latest | best_sellers | sale | most_viewed | featured | random`

## دیتابیس
| چه | کجا |
|---|---|
| تنظیمات و محتوای ماژول‌ها (استوری، بنر، دسته‌ها…) | گزینه‌ی `wsk_options` |
| آمار روزانه‌ی کلیک بنر/دسته، نمایش استوری، بازدید محصول | جدول `{prefix}wsk_stats` (UPSERT اتمی، پاک‌سازی روزانه با WP-Cron) |
| شمارنده‌ی بازدید محصول (برای مرتب‌سازی «پربازدیدترین») | متای `_wsk_views` |

## تفاوت با اسنیپت‌های قبلی
- بدون `<style>/<script>` درون‌خطی؛ یک CSS و یک JS مشترک.
- اسلایدرها بدون Swiper (scroll-snap) → وابسته به بارگذاری قالب نیستند.
- مرتب‌سازی با متای ناقص محصولات را حذف نمی‌کرد؛ اینجا با `meta_query` از نوع `EXISTS OR NOT EXISTS` درست شده.
- ورود اجباری: Open Redirect از طریق کوکی برطرف شد (کوکی فقط پرچم است، مقصد همیشه صفحه‌ی تسویه‌حساب).
- همه‌ی خروجی‌ها Escape و همه‌ی ورودی‌ها طبق اسکیما Sanitize می‌شوند.

> اسنیپت‌های قدیمی را بعد از تست در WPCode غیرفعال کنید تا شورت‌کدها دوبار ثبت نشوند.
