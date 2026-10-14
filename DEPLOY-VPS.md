# رفع الموقع على سيرفر VPS (DigitalOcean) مع قاعدة بيانات MySQL

هيك الدراسات والملفات والحسابات بتضل محفوظة، مش متل Render المجاني اللي بيمسح كل شي مع كل نشر.

السكربت `deploy/install.sh` بيركّب الموقع **جنب** أي شي شغّال على السيرفر (متل نظام مهارات)، وما بيلمس غيره:
- PHP 8.3 مع إعدادات خاصة بهالموقع بس، و nginx و MySQL (كل واحد بس إذا مش موجود).
- قاعدة بيانات MySQL ومستخدم خاصين بهالموقع، بكلمة سر عشوائية. ما بيلمس قواعد البيانات التانية.
- الكود بمجلد `/var/www/projectsengineering`.
- ملف موقع واحد بالـnginx إله لحاله.

## التركيب (مرة وحدة)

1. ادخلي على السيرفر (DigitalOcean → Droplet → Access → Launch Droplet Console، أو `ssh root@IP`).
2. شوفي إذا في دومين بدك تستخدميه. إذا لا، الموقع بيشتغل على عنوان السيرفر مع البورت 8080.
3. الصقي:

```bash
curl -fsSL https://raw.githubusercontent.com/MaharatNET-hub/projectsengineering/main/deploy/install.sh -o install.sh
sudo bash install.sh
```

إذا المستودع خاص (private) والأمر الأول ما زبط، نزّلي الملف من GitHub وارفعيه بالـWinSCP، أو انسخي محتواه بملف `install.sh` على السيرفر، وبعدها شغّليه. السكربت بيطلب منك **GitHub token** للقراءة بس (من GitHub: Settings ← Developer settings ← Fine-grained tokens، صلاحية Contents: Read لهالمستودع). الـtoken ما بينحفظ على السيرفر.

مع دومين:

```bash
sudo DOMAIN=review.maharatnet.com bash install.sh
```

قبلها لازم الدومين يأشّر على IP السيرفر (سجل A). السكربت بيسألك إذا بدك HTTPS مجاني (Let's Encrypt).

بالآخر بيطبعلك:
- رابط الموقع ورابط لوحة الإدارة `/v2/admin`.
- إيميل وكلمة سر المدير. **غيّريها** من «حسابي» بعد أول دخول.

## الإيميل

افتحي `/var/www/projectsengineering/.env` وعبّي إعدادات البريد (SMTP)، مثلاً:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=review@maharatnet.com
MAIL_FROM_NAME="Maharat Engineering"
```

بعدها شغّلي أمر التحديث تحت لتنطبق الإعدادات.

## التحديث (كل ما ندمج شي جديد على main)

```bash
sudo bash /var/www/projectsengineering/deploy/update.sh
```

بيجيب آخر نسخة، وبيضيف الجداول الجديدة إذا في، وبيحافظ على كل البيانات والملفات.

## النسخ الاحتياطي

- قاعدة البيانات: `projectsengineering` (أضيفيها لسكربت النسخ الاحتياطي الموجود عندك).
- الملفات المرفوعة والتقارير: `/var/www/projectsengineering/storage/app`.
