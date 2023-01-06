# php-cron-requests-events EXAMPLES

## Обработчик событий CRON
### Пример из файла: callback_cron.php

Данный файл будет запущен по расписанию, путь к файлу указан в поле $cron_jobs[$job_process_id]['callback']

#### Переменные
- $cron_session, хранит служебные поля сессии каждого задания $cron_jobs в отдельности
- $job_process_id, содержит порядковый номер задания из $cron_jobs

Переменные сохраниют свои значения между запусками задач по расписанию, но до тех пор пока поля в $cron_jobs[$job_process_id] не будут изменены.
```
Array // $cron_session
(
    [1] => Array
        (
            [md5] => 6e2ab79f4e8f5056a4f6a59475ffc31a
            [last_update] => 1673029671
            [complete] => 1
        )

    [start_counter] => 11450
)
```


## IPC (Inter-process communication) межпроцессное взаимодействие.
### Пример из файла: callback_addressed_queue_example.php

- вызов queue_address_manager_extend(true); // создает список микро задач и помещает их в очередь.
- вызов queue_address_manager_extend(false); // запускает обработчик микро задачи.

#### Сценарий выполнения:
1. Добавляем в общий список задач, событие для запуска создания очереди микро задач - workers. Обработчик события поместит микро задачи в очередь, длина которой ограничена только размером дискового пространства на сервере.
2. Добавляем в общий список задач, событие для запуска обработчиков микро задач - handlers. Событие CRON job 3 запускает парелелльно несколько процессов, каждый из которых получает из общей очереди микро задачи, и немедленно их исполняет.


#### Функции
```
// value - переменная для помещения в стек
// frame_size - размер кадра, в байтах
// frame_cursor - адрес, смещение в файле
// callback - вызывает функцию во время блокировки файла очереди
queue_address_push($value, $frame_size= false, $frame_cursor= false, $callback= false); // поместить в очередь
```
1. блокирующая операция
2. ожидает освобождения файла очереди
3. блокирует файл очереди и добавляет в конец файла кадр с данными
4. после чего освобождает файл отдавая его другому процессу
5. позволяет поместить кадр в любую позицию файла очереди

```
// frame_size - размер кадра, в байтах
// frame_cursor - адрес, смещение в файле
// frame_replace - переменная для замены
// callback - вызывает функцию во время блокировки файла очереди
$multicore_long_time_micro_job= queue_address_pop($frame_size, $frame_cursor= false, $frame_replace= false, $callback= false); // забрать из очереди
```
1. блокирующая операция
2. ожидает освобождения файла очереди
3. блокирует файл очереди и читает в конце файла кадр с данными
4. усекает файл, уменьшая файл на размер считанных данных
4. после чего освобождает файл отдавая его другому процессу
5. позволяет забрать\заменить кадр в любой позиции файла очереди


#### Структура загрузочной записи
```
// Reserved index struct
$boot= [ // 0-3 sector, frame size 4096
	'workers'=> [], // array process_id values
	'handlers'=> [], // array process_id values
	'system_variables'=> [],
	'reserved'=>[],
	'index_offset' => 4097, // data index offset
	'index_frame_size' => 1024 * 16, // data index frame size 16Kb
	'data_offset' => 1024 * 16 + 4098, // data offset
	'data_frame_size' => $frame_size, // data frame size
];
```



#### Структура IPC кадра
```
Array // Данные из нулевого фрейма отработавшей задачи
(
    [workers] => Array
        (
            [6112] => Array
                (
                    [process_id] => 6112
                    [last_update] => 1673041802.5961
                )

        )

    [handlers] => Array
        (
            [6175] => Array
                (
                    [process_id] => 6175
                    [last_update] => 1673041811.7822
                    [count_start] => 279
                    [last_start] => 1673041812.59
                )

            [6178] => Array
                (
                    [process_id] => 6178
                    [last_update] => 1673041811.8563
                    [count_start] => 262
                    [last_start] => 1673041812.5891
                )

            [6180] => Array
                (
                    [process_id] => 6180
                    [last_update] => 1673041811.9494
                    [count_start] => 239
                    [last_start] => 1673041812.5873
                )

            [6181] => Array
                (
                    [process_id] => 6181
                    [last_update] => 1673041812.0234
                    [count_start] => 224
                    [last_start] => 1673041812.5861
                )

        )

    [system_variables] => Array
        (
        )

    [reserved] => Array
        (
        )

    [index_offset] => 4097
    [index_frame_size] => 16384
    [data_offset] => 20482
    [data_frame_size] => 95
)
```
