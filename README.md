<div dir="rtl">

# WorldControl

مدیریت کامل ورلدها و سرور برای PocketMine-MP 5، با پنل فرم تمیز و دو زبان **فارسی** و **انگلیسی**.

قوانین هر ورلد (کندن و گذاشتن بلاک، پی‌وی‌پی، دمیج سقوط، گرسنگی، ...)، گیم‌مود اجباری، قفل تایم، همیشه اسپاون،
مرز ورلد، قفل ورلد، پیام خوش‌آمد، ساخت و لود ورلد و خیلی چیزهای دیگر؛ همه از داخل بازی با `/wc`.
متن فارسی با [libPersianText](https://github.com/ApexMine/libPersianText) داخل بازی درست (چسبیده و راست‌به‌چپ) نمایش داده می‌شود.

[English ↓](#english)

| | |
|---|---|
| API | PocketMine-MP 5 |
| وابستگی | ندارد |
| زبان‌ها | فارسی، انگلیسی (هر بازیکن زبان خودش را انتخاب می‌کند) |
| لایسنس | MIT |

---

## نصب

1. فایل `WorldControl.phar` را از بخش [Releases](https://github.com/ApexMine/WorldControl/releases) دانلود کنید و در پوشه `plugins/` سرور بگذارید.
2. سرور را روشن کنید. تمام! با `/wc` پنل باز می‌شود.

زبان پیش‌فرض را در `plugin_data/WorldControl/config.yml` با `language: fa` فارسی کنید. بازیکن‌هایی که زبان بازی‌شان فارسی
است خودکار فارسی می‌گیرند و هر کس می‌تواند با `/wc lang` زبان خودش را عوض کند.

---

## پنل

`/wc` را بزنید:

- **همین ورلد / همه ورلدها:** قوانین هر ورلد در ۶ صفحه: ساخت‌وساز، مبارزه، بازیکن‌ها، گیم‌مود و تایم، دسترسی و مرز، پیام‌ها.
  تلپورت، تنظیم اسپاون ورلد، ریست قوانین، لود و آنلود هم همین‌جاست.
- **قوانین پیش‌فرض:** روی همه ورلدهایی اجرا می‌شود که خودشان آن قانون را تغییر نداده‌اند. مثلا قفل تایم اینجا = قفل تایم کل سرور.
- **سرور و اسپاون:** اسپاون سرور، همیشه اسپاون، ریسپاون در اسپاون، دستور `/spawn`، پیام ورود و خروج.
- **مدیریت ورلدها:** ساخت ورلد (normal، flat، nether و هر ژنراتور دیگری که سرور دارد)، لود و آنلود.
- **حالت بیلدر:** تا وقتی روشن است هیچ قانونی روی شما اجرا نمی‌شود.
- **زبان**

قانون‌هایی که یک ورلد تغییر داده با `*` مشخص می‌شوند.

---

## مثال‌ها

<div dir="ltr">

```
/wc alwaysspawn set            # اسپاون سرور همین‌جا
/wc alwaysspawn on             # هر بار ورود به اسپاون برود
/wc alwaysspawn off

/wc time lobby noon            # تایم ورلد lobby همیشه ظهر
/wc time default day           # تایم کل سرور همیشه روز
/wc time lobby off             # آزاد کردن تایم

/wc set lobby break off        # بستن کندن بلاک در lobby
/wc set lobby place off
/wc set lobby pvp off
/wc set lobby fall-damage off
/wc set lobby hunger off

/wc gamemode creative_world creative   # همه در این ورلد کریتیو
/wc gamemode creative_world off

/wc set pvp_arena keep-inventory on
/wc set lobby void-rescue on
/wc set lobby border 200
/wc set event locked on
/wc set lobby blocked-commands fly, tpa, home
/wc set lobby welcome-title §bApex§fMine
```

</div>

به جای اسم ورلد می‌شود `here` (ورلدی که در آن هستید) یا `default` (قوانین پیش‌فرض همه ورلدها) نوشت.

---

## دستورها

| دستور | کار |
|---|---|
| `/wc` | باز کردن پنل |
| `/wc help` | راهنما |
| `/wc world [world]` | پنل یک ورلد |
| `/wc info [world]` | دیدن همه قوانین یک ورلد |
| `/wc set <world> <rule> <value>` | تغییر یک قانون |
| `/wc unset <world> <rule>` | برگشت یک قانون به پیش‌فرض |
| `/wc reset <world>` | حذف همه تغییرات یک ورلد |
| `/wc time <world> <value>` | قفل تایم: `sunrise` `day` `noon` `sunset` `night` `midnight`، عدد 0 تا 23999 یا `off` |
| `/wc gamemode <world> <mode>` | گیم‌مود اجباری یا `off` |
| `/wc rules` | لیست همه قوانین و مقدارهایشان |
| `/wc alwaysspawn <on/off/set/status>` | همیشه اسپاون |
| `/wc setspawn` | اسپاون سرور همین‌جا |
| `/wc setworldspawn` | اسپاون همین ورلد همین‌جا |
| `/wc tp <world> [player]` | تلپورت به ورلد |
| `/wc list` | لیست ورلدها |
| `/wc create <name> [generator] [seed]` | ساخت ورلد |
| `/wc load <world>` / `/wc unload <world>` | لود و آنلود ورلد |
| `/wc builder` | حالت بیلدر |
| `/wc lang [en/fa]` | تغییر زبان (برای همه بازیکن‌ها) |
| `/wc reload` | خواندن دوباره همه فایل‌ها |
| `/spawn` (`/lobby`، `/hub`) | رفتن به اسپاون سرور |

---

## قوانین

همه قانون‌های روشن/خاموش یعنی «مجاز است؟». مقدار پیش‌فرض همه، رفتار عادی ماینکرفت است.

| قانون | پیش‌فرض | کار |
|---|---|---|
| `break` | on | کندن بلاک |
| `place` | on | گذاشتن بلاک و ابزارهایی مثل فندک، بیلچه، بیل، استخوان‌پودر |
| `interact` | on | استفاده از صندوق، در، دکمه، اهرم، کوره، آنویل و ... |
| `bucket` | on | پر و خالی کردن سطل |
| `explosions` | on | خراب شدن بلاک با انفجار |
| `fire-spread` | on | پخش شدن آتش |
| `liquid-flow` | on | جاری شدن آب و لاوا |
| `leaves-decay` | on | ریختن برگ‌ها |
| `crop-growth` | on | رشد گیاهان |
| `pvp` | on | آسیب زدن بازیکن‌ها به هم (با تیر هم) |
| `damage` | on | هر نوع آسیب (خاموش = ضدضربه) |
| `fall-damage` | on | دمیج سقوط |
| `void-rescue` | off | افتادن در ووید = تلپورت به اسپاون ورلد |
| `hunger` | on | گرسنگی |
| `drop-items` | on | انداختن آیتم |
| `pickup-items` | on | برداشتن آیتم |
| `fly` | off | پرواز در سروایوال |
| `keep-inventory` | off | حفظ آیتم‌ها بعد از مرگ |
| `keep-xp` | off | حفظ XP بعد از مرگ |
| `chat` | on | چت |
| `world-chat` | off | چت فقط به بازیکن‌های همین ورلد برسد |
| `gamemode` | none | گیم‌مود اجباری |
| `difficulty` | none | سختی ورلد |
| `time` | off | قفل تایم |
| `locked` | off | کسی نتواند وارد ورلد شود |
| `max-players` | 0 | حداکثر بازیکن هم‌زمان (0 = نامحدود) |
| `border` | 0 | شعاع مرز دور اسپاون ورلد (0 = بدون مرز) |
| `welcome-title` / `welcome-subtitle` | - | تایتل موقع ورود؛ `{player}` `{world}` `{online}` کار می‌کنند |
| `welcome-message` | - | پیام چت موقع ورود؛ `\n` خط جدید |
| `blocked-commands` | - | دستورهای ممنوع در این ورلد، با کاما جدا |

---

## دسترسی‌ها

| دسترسی | پیش‌فرض | کار |
|---|---|---|
| `worldcontrol.command` | همه | استفاده از `/wc` (بدون ادمین فقط انتخاب زبان) |
| `worldcontrol.admin` | اوپی | همه امکانات پنل و دستورها |
| `worldcontrol.builder` | اوپی | حالت بیلدر |
| `worldcontrol.spawn` | همه | دستور `/spawn` |
| `worldcontrol.bypass.build` | هیچ‌کس | نادیده گرفتن break، place، interact، bucket، drop و pickup |
| `worldcontrol.bypass.combat` | هیچ‌کس | زدن بازیکن‌ها در ورلد بدون پی‌وی‌پی |
| `worldcontrol.bypass.gamemode` | هیچ‌کس | نگه داشتن گیم‌مود خودش |
| `worldcontrol.bypass.commands` | هیچ‌کس | استفاده از دستورهای ممنوع |
| `worldcontrol.bypass.chat` | هیچ‌کس | چت وقتی چت بسته است |
| `worldcontrol.bypass.border` | هیچ‌کس | رد شدن از مرز |
| `worldcontrol.bypass.enter` | هیچ‌کس | ورود به ورلد قفل یا پر |
| `worldcontrol.bypass.all` | هیچ‌کس | همه موارد بالا |

قوانین به طور پیش‌فرض روی اوپی‌ها هم اجرا می‌شوند تا بتوانید نتیجه را ببینید. برای ساختن `/wc builder` را بزنید
یا به استف‌ها دسترسی bypass بدهید.

---

## فایل‌ها

همه در `plugin_data/WorldControl/`:

| فایل | محتوا |
|---|---|
| `config.yml` | زبان، طول خط فارسی، نوع پیام خطا (actionbar/tip/chat/none)، اسم‌های دیگر `/spawn` |
| `worlds.yml` | قوانین (`defaults` + فقط تغییرات هر ورلد زیر `worlds`) |
| `server.yml` | اسپاون سرور و همیشه اسپاون |
| `players.yml` | زبانی که هر بازیکن انتخاب کرده |
| `lang/*.yml` | متن‌ها. برای زبان جدید `en.yml` را کپی و ترجمه کنید |

بهتر است همه چیز را از داخل بازی تغییر دهید. اگر فایلی را دستی ویرایش کردید، `/wc reload` بزنید.

---

## برای برنامه‌نویس‌ها

<div dir="ltr">

```php
use ApexMine\WorldControl\WorldControl;
use ApexMine\WorldControl\rule\Rule;

$wc = WorldControl::getInstance();
$wc->rulesOf($player->getWorld())->allows(Rule::PVP);          // bool
$wc->getRuleManager()->set("lobby", Rule::TIME, 6000);           // lock lobby at noon
$wc->getRuleManager()->set(null, Rule::HUNGER, false);           // null = default rules
```

</div>

ساخت phar از سورس: `php -d phar.readonly=0 tools/build-phar.php`

</div>

---

## English

Complete world & server management for PocketMine-MP 5, with a clean form panel in **English** and **Persian**.

Per-world rules (breaking, placing, PvP, fall damage, hunger ...), forced gamemode, time lock, always-spawn, world
border, locked worlds, welcome titles, creating and loading worlds and more, all in game with `/wc`. Persian text is
shaped and shown right-to-left with [libPersianText](https://github.com/ApexMine/libPersianText).

### Install

Download `WorldControl.phar` from [Releases](https://github.com/ApexMine/WorldControl/releases), put it in `plugins/`
and start the server. `/wc` opens the panel. No dependencies.

Every player gets their own language: picked with `/wc lang`, otherwise detected from their game language, otherwise
`language` in `config.yml`.

### How rules work

`worlds.yml` has **default rules** for every world, and each world only stores the rules it changes. So locking the
time in the defaults locks it on the whole server, except in worlds that set their own time. On/off rules always mean
"is this allowed?", and every default is vanilla behavior.

Rules: `break`, `place`, `interact`, `bucket`, `explosions`, `fire-spread`, `liquid-flow`, `leaves-decay`,
`crop-growth`, `pvp`, `damage`, `fall-damage`, `void-rescue`, `hunger`, `drop-items`, `pickup-items`, `fly`,
`keep-inventory`, `keep-xp`, `chat`, `world-chat`, `gamemode`, `difficulty`, `time`, `locked`, `max-players`,
`border`, `welcome-title`, `welcome-subtitle`, `welcome-message`, `blocked-commands`. See the Persian table above
or `/wc rules` for what each one does.

### Commands

```
/wc                                    open the panel
/wc set <world|default|here> <rule> <value>
/wc unset <world> <rule>               /wc reset <world>
/wc time <world> <day|noon|night|midnight|sunrise|sunset|0-23999|off>
/wc gamemode <world> <survival|creative|adventure|spectator|off>
/wc alwaysspawn <on|off|set|status>    /wc setspawn    /wc setworldspawn
/wc info [world]   /wc rules   /wc list   /wc tp <world> [player]
/wc create <name> [generator] [seed]   /wc load <world>   /wc unload <world>
/wc builder   /wc lang [en|fa]   /wc reload
/spawn (/lobby, /hub)
```

### Permissions

`worldcontrol.admin` (op) for everything, `worldcontrol.builder` (op) for builder mode, `worldcontrol.command` and
`worldcontrol.spawn` (everyone). Bypass permissions (nobody by default): `worldcontrol.bypass.build`, `.combat`,
`.gamemode`, `.commands`, `.chat`, `.border`, `.enter` and `.all`. Rules apply to ops too, so use `/wc builder` to
build in protected worlds.

### License

[MIT](LICENSE). Bundles [libPersianText](https://github.com/ApexMine/libPersianText) (MIT) under
`src/ApexMine/WorldControl/libs/`.
