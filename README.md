# All In Streams

კომპანიებისა და სტრიმერების თანამშრომლობის პლატფორმა, რომელსაც All In Streams მართავს: ბრიფი → განხილვა → შერჩევა → მოწვევა → შეთავაზება → ხელშეკრულებები → კამპანია → რეპორტინგი.

პროექტი მდებარეობს `platform/` საქაღალდეში. სტეკი: Laravel 12, Blade, Livewire 4, Tailwind CSS 4, Filament 5 და MySQL. ლოკალური გაშვებისთვის გამოიყენება უკვე დაყენებული XAMPP-ის MariaDB 10.4, რომელიც MySQL-ის პროტოკოლს იყენებს. სამუშაო მონაცემები ცალკე ინახება `.runtime/mysql`-ში; XAMPP-ის არსებული ბაზა და სერვისები არ იცვლება.

## პირველი გაშვება Windows-ზე

საჭიროა PHP 8.2+, Composer, Node.js/NPM და XAMPP/MariaDB (`C:\xampp\mysql`). ბრძანებები გაუშვით ამ საქაღალდიდან PowerShell-ში:

```powershell
cd platform
php -d extension=intl "C:\ProgramData\ComposerSetup\bin\composer.phar" install
npm ci
Copy-Item .env.example .env
php -d extension=intl artisan key:generate
.\scripts\Start-Local.ps1 -DatabaseOnly
php -d extension=intl artisan migrate --seed
npm run build
.\scripts\Start-Local.ps1
```

უკვე მომზადებული პროექტის ხელახლა გასაშვებად საკმარისია:

```powershell
.\platform\scripts\Start-Local.ps1
```

საიტი იხსნება მისამართზე **http://127.0.0.1:8000**. პროცესები ფარულად იწყება; ლოგები `.runtime/`-შია. გაჩერება მონაცემებს ინარჩუნებს:

```powershell
.\platform\scripts\Stop-Local.ps1
```

საწყისი დემო ანგარიშები `migrate --seed`-ის შემდეგ:

| სივრცე | მისამართი | ელფოსტა |
| --- | --- | --- |
| კომპანია | `/login` | `brand@allinstreams.test` |
| სტრიმერი | `/login` | `creator@allinstreams.test` |
| ადმინისტრატორი | `/admin` | `admin@allinstreams.test` |

სამივე დემო ანგარიშის პაროლია `StreamDemo!2026`. დემო კომპანიები, სტრიმერები და ხელშეკრულებები გამოგონილია.

თუ PowerShell-ის პოლიტიკა უშლის ხელს სკრიპტს, გამოიყენეთ მხოლოდ ამ გაშვებისთვის:

```powershell
powershell -ExecutionPolicy Bypass -File ".\platform\scripts\Start-Local.ps1"
```

სხვა MariaDB მისამართისთვის გამოიყენეთ `-MySqlHome`, სხვა PHP-სთვის — `-PhpPath`. სკრიპტები იყენებს მხოლოდ ამ პროექტის პროცესებსა და პორტებს `3307`/`8000`.

## მონაცემთა ბაზა და შემოწმება

ლოკალური `.env.example` იყენებს ბაზას `all_in_streams`, მომხმარებელს `stream_app` და მხოლოდ დეველოპმენტისთვის განკუთვნილ პაროლს `StreamLocal!2026`. საწარმოო MySQL-ისთვის შეცვალეთ `.env`-ში `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` და გამოიყენეთ თქვენი სერვერის მონაცემები. `.env` და `.runtime` არ იტვირთება Git-ში.

ამ გარემოში `intl` ბრძანების პარამეტრით ირთვება; PHP-ის გლობალური კონფიგურაციის შეცვლა საჭირო არ არის. Composer-ის სხვა მდებარეობის შემთხვევაში შეცვალეთ ზემოთ მოცემული `composer.phar`-ის მისამართი. ლოკალური სკრიპტი უშუალოდ PHP-ის HTTP სერვერს იწყებს, რათა `intl` ვებმოთხოვნებისთვისაც ჩართული იყოს.

```powershell
cd platform
php -d extension=intl vendor/phpunit/phpunit/phpunit
npm run build
```

შემოწმებულია **24 ტესტი და 139 მტკიცება**: როლები, კამპანიის გადასვლები, მონაცემების განცალკევება, დოკუმენტების წვდომა, ფორმები და დემო მონაცემების გარემოს შეზღუდვა. ლოკალური HTTP სერვერით ასევე შემოწმებულია **15 ავტორიზებული გვერდი** კომპანიის, სტრიმერისა და ადმინისტრატორისთვის.

რეგისტრაციისა და კამპანიის მოქმედებების რეალური მონაცემები ბაზაში ინახება. ელფოსტა ამ ეტაპზე ლოგში იწერება (`MAIL_MAILER=log`); გადახდის მზადყოფნასა და ხელმოწერის სტატუსს ადმინისტრატორი მართავს. გადახდების ან ელექტრონული ხელმოწერის პროვაიდერი მიერთებული არ არის. რეპორტები დოკუმენტებად იტვირთება. საწარმოო გაშვებამდე ამ გარე სერვისების მონაცემები ცალკე უნდა დაკონფიგურირდეს.
