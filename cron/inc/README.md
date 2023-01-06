# IPC (Inter-process communication) межпроцессное взаимодействие.
## Пример из файла: callback_addressed_queue_example.php

В данном примере IPC реализован по типу мьютекс, все участвующие процессы получают доступ к файлу данных в монопольном режиме.

Процесс захватывает файл данных с помощью системного механизма консультативной блокировки файла, все остальные процессы выстраиваются в очередь в ожидании снятия блокировки.

Чтение\запись производится только в начале и в конце файла, нулевой кадр содержит данные для передачи между процессами. Кадры выстраиваются в очередь образуя стек, в режиме LIFO процессы опустошают очередь с конца.

Во взаимодействии принимает участие Page Cache Linux Kernel, чтение\запись первых и последних секторов файла всегда будет кэшировано операционной системой. 
Таким образом передача данных между процессами будет с минимальными задержками, на уровне Shared Memory.

#### Структура загрузочной записи
```
// Reserved index struct
$boot= [ // 0 sector, frame size 4096
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
            [14575] => Array
                (
                    [process_id] => 14575
                    [last_update] => 1672897202.3705
                )

        )

    [handlers] => Array
        (
            [14640] => Array
                (
                    [process_id] => 14640
                    [last_update] => 1672897211.6983
                )

            [14643] => Array
                (
                    [process_id] => 14643
                    [last_update] => 1672897211.7723
                )

            [14645] => Array
                (
                    [process_id] => 14645
                    [last_update] => 1672897211.8554
                )

            [14646] => Array
                (
                    [process_id] => 14646
                    [last_update] => 1672897211.9214
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
