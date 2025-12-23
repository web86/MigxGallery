# MigxGallery
🧩 Назначение плагина
1) это плагин для MODX 2.8+, который автоматически:
2) принимает множественную загрузку изображений через MIGX
3) временно хранит файлы в _tmp

При сохранении ресурса:
1) переносит изображения в папку ресурса
2) нормализует имена (alias-1.jpg, alias-1-L.jpg, alias-1-S.jpg)
3) создаёт дополнительные размеры

Поддерживает:

1) drag-reorder в MIGX
2) удаление изображений
3) корректную работу превью
4) очищает лишние файлы на диске

🏗️ Общая архитектура
Upload → _tmp
          ↓
      OnDocFormSave
          ↓
  images/galleries/{alias}/
          ↓
     MIGX хранит пути

Важный принцип

_tmp — это staging-зона,
gallery — финальное хранилище.
MIGX никогда не работает напрямую с _tmp.

📁 Структура файлов
images/
└── galleries/
    ├── _tmp/   ← временные загрузки
    └── resource-alias/
        ├── resource-alias-1.jpg
        ├── resource-alias-1-L.jpg
        ├── resource-alias-1-S.jpg
        ├── resource-alias-2.jpg
        └── ...

⚙️ Что нужно настроить в MODX
1️⃣ MIGX TV
Создать TV типа MIGX (например GalleryMigx)
- Поле image
- inputTVtype = image
- Media Source = config
В конфигурации:
- context: web
- sourceId: Media Source с пустым basePath/baseUrl
Создать кнопку загрузки файлов пачкой 
- Action Buttons → Upload files
- запретить кнопку добавления по одному

2️⃣ Media Sources

🔹 Media Source #1 — _tmp

Используется ТОЛЬКО для upload
basePath = images/galleries/_tmp/
baseUrl  = /images/galleries/_tmp/

Назначается в TV которое выводит MIGX

🔹 Media Source #2 — Root / Config Source

Используется для превью и чтения файлов
- basePath = (пусто)
- baseUrl  = (пусто)

Используется через:
MIGX Field → Media Source → config
sourceId = <id этого источника>

3️⃣ Подключение плагина
Плагин должен быть подключён к событиям:

✅ OnDocFormSave
✅ OnBeforeEmptyTrash

(опционально) OnDocFormPrerender — если потребуется чистка _tmp при входе

🔄 Принцип работы плагина

🔹 При сохранении ресурса (OnDocFormSave)

Получает JSON MIGX
Определяет:
- есть ли новые файлы (_tmp)
- был ли drag-reorder

Для каждого элемента:
- вычисляет правильное имя (alias-N)

При необходимости:
- ресайзит изображение
- создаёт версии -L, -S
- Всегда пересобирает MIGX в новом порядке

Синхронизирует файловую систему:
- удаляет файлы, которых нет в MIGX
- Сохраняет MIGX через modTemplateVarResource
❗ без рекурсии
❗ без повторного сохранения ресурса

🔹 При удалении ресурса (OnBeforeEmptyTrash)
Удаляет папку images/galleries/{alias} целиком

🧠 Ключевые особенности и решения
✔️ Почему нет рекурсии

НЕ используется $tv->save()
MIGX сохраняется напрямую через modTemplateVarResource

✔️ Почему drag-reorder работает

MIGX пересобирается всегда
обработка файлов и порядок — разделены

✔️ Почему превью стабильны

phpThumb читает файлы не через _tmp
Media Source указывает на root

✔️ Почему можно чистить _tmp

_tmp не используется для превью
все финальные пути указывают на images/galleries/{alias}

🧪 Ограничения и допущения

Папка images/galleries/{alias} принадлежит плагину
Внутрь не нужно класть сторонние файлы
Alias ресурса используется как идентификатор папки
Поддерживаемые форматы: jpg, jpeg, png

🛠️ Что можно доработать в будущем

WebP / AVIF
watermark
_tmp/{session} или _tmp/{resource}
крон-очистка _tmp
вынос в класс или отдельный компонент
