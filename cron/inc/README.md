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

В данном примере IPC реализован по типу мьютекс, все участвующие процессы получают доступ к файлу данных в монопольном режиме.

Процесс захватывает файл данных с помощью системного механизма консультативной блокировки файла, все остальные процессы выстраиваются в очередь в ожидании снятия блокировки.

Чтение\запись производится только в начале и в конце файла, нулевой кадр содержит данные для передачи между процессами. Кадры выстраиваются в очередь образуя стек, в режиме LIFO процессы опустошают очередь с конца.

Во взаимодействии принимает участие Page Cache Linux Kernel, чтение\запись первых и последних секторов файла, по умолчанию всегда будет кэшировано операционной системой. 
Таким образом передача данных между процессами будет с минимальными задержками, на уровне Shared Memory.

В приведенном примере есть реализация индексной базы данных: 
- все кадры располагаются в файле со смещеним
- есть возможность хранения индекса в файле данных
- чтение\запись любого кадра по смещению в пределах файла данных
- файл может быть любого размера



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
Array
(
    [workers] => Array
        (
            [11807] => Array
                (
                    [process_id] => 11807
                    [last_update] => 1673039402.2942
                )

        )

    [handlers] => Array
        (
            [11872] => Array
                (
                    [process_id] => 11872
                    [last_update] => 1673039411.5832
                    [count_start] => 271
                )

            [11874] => Array
                (
                    [process_id] => 11874
                    [last_update] => 1673039411.6445
                    [count_start] => 256
                )

            [11876] => Array
                (
                    [process_id] => 11876
                    [last_update] => 1673039411.6997
                    [count_start] => 244
                )

            [11877] => Array
                (
                    [process_id] => 11877
                    [last_update] => 1673039411.743
                    [count_start] => 233
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

