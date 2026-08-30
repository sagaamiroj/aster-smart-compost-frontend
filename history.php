<?php

// ============================================================
// ASTER SMART COMPOST API
// HISTORY ENDPOINT
// ============================================================
//
// Endpoint:
// GET /api/v1/compost/history.php
//
// Query:
// ?node_id=1
// ?type=raw
// ?type=aggregated
// ?limit=100
//
// Kombinasi:
// ?node_id=1&type=raw&limit=100
//
// Response:
// {
//   "success": true,
//   "raw": [...],
//   "aggregated": [...],
//   "count": {
//      "raw": 100,
//      "aggregated": 0
//   }
// }
//
// ============================================================


// ============================================================
// CORS
// ============================================================

$allowedOrigin =
    "https://aster-smart-compost-frontend-production.up.railway.app";

header(
    "Access-Control-Allow-Origin: " . $allowedOrigin
);

header(
    "Access-Control-Allow-Methods: GET, OPTIONS"
);

header(
    "Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With"
);

header(
    "Access-Control-Max-Age: 86400"
);

header(
    "Vary: Origin"
);

header(
    "Content-Type: application/json; charset=utf-8"
);


// ============================================================
// OPTIONS / PREFLIGHT
// ============================================================

if (
    $_SERVER["REQUEST_METHOD"] === "OPTIONS"
) {

    http_response_code(204);

    exit;
}


// ============================================================
// ONLY GET
// ============================================================

if (
    $_SERVER["REQUEST_METHOD"] !== "GET"
) {

    http_response_code(405);

    echo json_encode(
        [
            "success" => false,
            "message" => "Method not allowed",
            "allowed_method" => "GET"
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


// ============================================================
// TIMEZONE
// ============================================================

date_default_timezone_set(
    "Asia/Jakarta"
);


// ============================================================
// RESPONSE HELPER
// ============================================================

function responseJson(
    int $httpCode,
    array $data
) {

    http_response_code(
        $httpCode
    );

    echo json_encode(
        $data,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


// ============================================================
// DATABASE ENVIRONMENT
// ============================================================

$DB_HOST =
    getenv("MYSQLHOST");

$DB_PORT =
    getenv("MYSQLPORT");

$DB_NAME =
    getenv("MYSQLDATABASE");

$DB_USER =
    getenv("MYSQLUSER");

$DB_PASS =
    getenv("MYSQLPASSWORD");


// ============================================================
// CHECK DATABASE ENVIRONMENT
// ============================================================

if (
    $DB_HOST === false ||
    $DB_PORT === false ||
    $DB_NAME === false ||
    $DB_USER === false ||
    $DB_PASS === false
) {

    responseJson(
        500,
        [
            "success" => false,
            "message" =>
                "Database environment variables are incomplete"
        ]
    );
}


// ============================================================
// DATABASE CONNECTION
// ============================================================

mysqli_report(
    MYSQLI_REPORT_OFF
);


$conn = new mysqli(
    $DB_HOST,
    $DB_USER,
    $DB_PASS,
    $DB_NAME,
    (int)$DB_PORT
);


// ============================================================
// CHECK DATABASE CONNECTION
// ============================================================

if (
    $conn->connect_error
) {

    responseJson(
        500,
        [
            "success" => false,
            "message" =>
                "Database connection failed",
            "error" =>
                $conn->connect_error
        ]
    );
}


// ============================================================
// CHARACTER SET
// ============================================================

if (
    !$conn->set_charset("utf8mb4")
) {

    $conn->close();

    responseJson(
        500,
        [
            "success" => false,
            "message" =>
                "Failed to configure database character set"
        ]
    );
}


// ============================================================
// UTC → WIB
// ============================================================

function utcToWIB(
    $datetime
) {

    if (
        $datetime === null ||
        $datetime === ""
    ) {

        return null;
    }

    try {

        $date = new DateTime(
            $datetime,
            new DateTimeZone("UTC")
        );

        $date->setTimezone(
            new DateTimeZone("Asia/Jakarta")
        );

        return $date->format(
            "Y-m-d H:i:s"
        );

    } catch (
        Exception $e
    ) {

        return null;
    }
}


// ============================================================
// QUERY PARAMETERS
// ============================================================


// ============================================================
// NODE ID
// ============================================================

$nodeId = null;

if (
    isset($_GET["node_id"]) &&
    $_GET["node_id"] !== ""
) {

    $nodeId =
        filter_var(
            $_GET["node_id"],
            FILTER_VALIDATE_INT
        );

    if (
        $nodeId === false ||
        $nodeId < 1 ||
        $nodeId > 5
    ) {

        $conn->close();

        responseJson(
            400,
            [
                "success" => false,
                "message" =>
                    "node_id must be an integer between 1 and 5"
            ]
        );
    }
}


// ============================================================
// TYPE
// ============================================================

$type =
    $_GET["type"] ?? "all";

$type =
    strtolower(
        trim($type)
    );


$allowedTypes = [
    "all",
    "raw",
    "aggregated"
];


if (
    !in_array(
        $type,
        $allowedTypes,
        true
    )
) {

    $conn->close();

    responseJson(
        400,
        [
            "success" => false,
            "message" =>
                "Invalid type",
            "allowed_type" =>
                $allowedTypes
        ]
    );
}


// ============================================================
// LIMIT
// ============================================================

$limit = 100;

if (
    isset($_GET["limit"]) &&
    $_GET["limit"] !== ""
) {

    $limit =
        filter_var(
            $_GET["limit"],
            FILTER_VALIDATE_INT
        );

    if (
        $limit === false ||
        $limit < 1 ||
        $limit > 1000
    ) {

        $conn->close();

        responseJson(
            400,
            [
                "success" => false,
                "message" =>
                    "limit must be between 1 and 1000"
            ]
        );
    }
}


// ============================================================
// INITIAL RESPONSE
// ============================================================

$response = [

    "success" => true,

    "message" =>
        "Compost history retrieved successfully",

    "server_time" =>
        date("Y-m-d H:i:s"),

    "timezone" =>
        "Asia/Jakarta",

    "filter" => [

        "node_id" =>
            $nodeId,

        "type" =>
            $type,

        "limit" =>
            $limit
    ],

    "raw" => [],

    "aggregated" => [],

    "count" => [

        "raw" => 0,

        "aggregated" => 0

    ]

];


// ============================================================
// RAW HISTORY
// ============================================================

if (
    $type === "all" ||
    $type === "raw"
) {

    if (
        $nodeId !== null
    ) {

        $sqlRaw = "

            SELECT
                id,
                node_id,
                recorded_at,
                sample_index,
                temperature,
                moisture,
                soil_adc,
                mq4_adc,
                temp_status,
                mq4_status

            FROM compost_raw

            WHERE node_id = ?

            ORDER BY
                recorded_at DESC,
                id DESC

            LIMIT ?

        ";

        $stmtRaw =
            $conn->prepare(
                $sqlRaw
            );

        if (
            !$stmtRaw
        ) {

            $conn->close();

            responseJson(
                500,
                [
                    "success" => false,
                    "message" =>
                        "Failed to prepare raw history query",
                    "error" =>
                        $conn->error
                ]
            );
        }

        $stmtRaw->bind_param(
            "ii",
            $nodeId,
            $limit
        );

    } else {

        $sqlRaw = "

            SELECT
                id,
                node_id,
                recorded_at,
                sample_index,
                temperature,
                moisture,
                soil_adc,
                mq4_adc,
                temp_status,
                mq4_status

            FROM compost_raw

            WHERE node_id BETWEEN 1 AND 5

            ORDER BY
                recorded_at DESC,
                id DESC

            LIMIT ?

        ";

        $stmtRaw =
            $conn->prepare(
                $sqlRaw
            );

        if (
            !$stmtRaw
        ) {

            $conn->close();

            responseJson(
                500,
                [
                    "success" => false,
                    "message" =>
                        "Failed to prepare raw history query",
                    "error" =>
                        $conn->error
                ]
            );
        }

        $stmtRaw->bind_param(
            "i",
            $limit
        );
    }


    // ========================================================
    // EXECUTE
    // ========================================================

    if (
        !$stmtRaw->execute()
    ) {

        $error =
            $stmtRaw->error;

        $stmtRaw->close();

        $conn->close();

        responseJson(
            500,
            [
                "success" => false,
                "message" =>
                    "Failed to retrieve raw history",
                "error" =>
                    $error
            ]
        );
    }


    // ========================================================
    // RESULT
    // ========================================================

    $resultRaw =
        $stmtRaw->get_result();

    if (
        !$resultRaw
    ) {

        $stmtRaw->close();

        $conn->close();

        responseJson(
            500,
            [
                "success" => false,
                "message" =>
                    "Failed to read raw history result"
            ]
        );
    }


    // ========================================================
    // PROCESS RAW
    // ========================================================

    while (
        $row =
            $resultRaw->fetch_assoc()
    ) {

        $response["raw"][] = [

            "id" =>
                intval(
                    $row["id"]
                ),

            "node_id" =>
                intval(
                    $row["node_id"]
                ),

            "recorded_at" =>
                utcToWIB(
                    $row["recorded_at"]
                ),

            "sample_index" =>
                intval(
                    $row["sample_index"]
                ),

            "temperature" =>
                floatval(
                    $row["temperature"]
                ),

            "moisture" =>
                floatval(
                    $row["moisture"]
                ),

            "soil_adc" =>
                intval(
                    $row["soil_adc"]
                ),

            "mq4_adc" =>
                intval(
                    $row["mq4_adc"]
                ),

            "temp_status" =>
                $row["temp_status"],

            "mq4_status" =>
                $row["mq4_status"]

        ];
    }


    $resultRaw->free();

    $stmtRaw->close();
}


// ============================================================
// AGGREGATED HISTORY
// ============================================================

if (
    $type === "all" ||
    $type === "aggregated"
) {

    if (
        $nodeId !== null
    ) {

        $sqlAggregated = "

            SELECT
                id,
                node_id,
                recorded_at,
                sample_index,
                temperature,
                moisture,
                soil_adc,
                mq4_adc,
                ch4_index,
                temp_status,
                compost_status,
                sample_count,
                daily_sample

            FROM compost_data

            WHERE node_id = ?

            ORDER BY
                recorded_at DESC,
                id DESC

            LIMIT ?

        ";

        $stmtAggregated =
            $conn->prepare(
                $sqlAggregated
            );

        if (
            !$stmtAggregated
        ) {

            $conn->close();

            responseJson(
                500,
                [
                    "success" => false,
                    "message" =>
                        "Failed to prepare aggregated history query",
                    "error" =>
                        $conn->error
                ]
            );
        }

        $stmtAggregated->bind_param(
            "ii",
            $nodeId,
            $limit
        );

    } else {

        $sqlAggregated = "

            SELECT
                id,
                node_id,
                recorded_at,
                sample_index,
                temperature,
                moisture,
                soil_adc,
                mq4_adc,
                ch4_index,
                temp_status,
                compost_status,
                sample_count,
                daily_sample

            FROM compost_data

            WHERE node_id BETWEEN 1 AND 5

            ORDER BY
                recorded_at DESC,
                id DESC

            LIMIT ?

        ";

        $stmtAggregated =
            $conn->prepare(
                $sqlAggregated
            );

        if (
            !$stmtAggregated
        ) {

            $conn->close();

            responseJson(
                500,
                [
                    "success" => false,
                    "message" =>
                        "Failed to prepare aggregated history query",
                    "error" =>
                        $conn->error
                ]
            );
        }

        $stmtAggregated->bind_param(
            "i",
            $limit
        );
    }


    // ========================================================
    // EXECUTE
    // ========================================================

    if (
        !$stmtAggregated->execute()
    ) {

        $error =
            $stmtAggregated->error;

        $stmtAggregated->close();

        $conn->close();

        responseJson(
            500,
            [
                "success" => false,
                "message" =>
                    "Failed to retrieve aggregated history",
                "error" =>
                    $error
            ]
        );
    }


    // ========================================================
    // RESULT
    // ========================================================

    $resultAggregated =
        $stmtAggregated->get_result();

    if (
        !$resultAggregated
    ) {

        $stmtAggregated->close();

        $conn->close();

        responseJson(
            500,
            [
                "success" => false,
                "message" =>
                    "Failed to read aggregated history result"
            ]
        );
    }


    // ========================================================
    // PROCESS AGGREGATED
    // ========================================================

    while (
        $row =
            $resultAggregated->fetch_assoc()
    ) {

        $response["aggregated"][] = [

            "id" =>
                intval(
                    $row["id"]
                ),

            "node_id" =>
                intval(
                    $row["node_id"]
                ),

            "recorded_at" =>
                utcToWIB(
                    $row["recorded_at"]
                ),

            "sample_index" =>
                intval(
                    $row["sample_index"]
                ),

            "temperature" =>
                floatval(
                    $row["temperature"]
                ),

            "moisture" =>
                floatval(
                    $row["moisture"]
                ),

            "soil_adc" =>
                intval(
                    $row["soil_adc"]
                ),

            "mq4_adc" =>
                intval(
                    $row["mq4_adc"]
                ),

            "ch4_index" =>
                intval(
                    $row["ch4_index"]
                ),

            "temp_status" =>
                $row["temp_status"],

            "compost_status" =>
                $row["compost_status"],

            "sample_count" =>
                intval(
                    $row["sample_count"]
                ),

            "daily_sample" =>
                intval(
                    $row["daily_sample"]
                )

        ];
    }


    $resultAggregated->free();

    $stmtAggregated->close();
}


// ============================================================
// FINAL COUNT
// ============================================================

$response["count"]["raw"] =
    count(
        $response["raw"]
    );


$response["count"]["aggregated"] =
    count(
        $response["aggregated"]
    );


// ============================================================
// DATABASE CLOSE
// ============================================================

$conn->close();


// ============================================================
// FINAL RESPONSE
// ============================================================

echo json_encode(
    $response,
    JSON_PRETTY_PRINT |
    JSON_UNESCAPED_UNICODE
);

?>
