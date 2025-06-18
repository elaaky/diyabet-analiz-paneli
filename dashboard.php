<?php
// Hata raporlamayı açarak geliştirme aşamasında tüm hataları görelim
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- 1. VERİTABANI BAĞLANTISI ---
$servername = "127.0.0.1";
$username = "root";
$password = "";
$database = "proje";

try {
    $baglanti = new mysqli($servername, $username, $password, $database);
    $baglanti->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    die("<h1>Veritabanı Bağlantı Hatası!</h1><p>Sunucuya bağlanılamadı. Lütfen ayarların doğru olduğundan emin olun.</p><p>Hata Detayı: " . $e->getMessage() . "</p>");
}

// --- 2. FİLTRE DEĞERLERİNİ GÜVENLİ BİR ŞEKİLDE ALMA ---
$yas_filtre = $_GET['yas'] ?? '';
$vki_filtre = $_GET['vki'] ?? '';
$gebelik_max_filtre_url = isset($_GET['gebelik_max']) ? (int)$_GET['gebelik_max'] : null;

$query_pregnancies_minmax_general = "SELECT MIN(gebelik_sayisi) as min_p, MAX(gebelik_sayisi) as max_p FROM kisiler";
$result_pregnancies_minmax_general = $baglanti->query($query_pregnancies_minmax_general);
$p_minmax_general = $result_pregnancies_minmax_general->fetch_assoc();
$pregnancies_min_general = $p_minmax_general['min_p'] ?? 0;
$pregnancies_max_general = $p_minmax_general['max_p'] ?? 17;
$gebelik_max_filtre = $gebelik_max_filtre_url ?? $pregnancies_max_general;

// --- 3. SQL SORGULARI İÇİN GÜVENLİ YAPI ---
$base_query = " FROM kisiler";
$where_clauses = [];
$params = [];
$types = "";

$where_clauses[] = "gebelik_sayisi <= ?";
$types .= "i";
$params[] = $gebelik_max_filtre;

if ($yas_filtre != '') {
    if ($yas_filtre == 'Less Than 30') $where_clauses[] = "yas < 30";
    elseif ($yas_filtre == '30-50') $where_clauses[] = "yas BETWEEN 30 AND 50";
    elseif ($yas_filtre == 'High Age') $where_clauses[] = "yas > 50";
}

if ($vki_filtre != '') {
    if ($vki_filtre == 'Normal') $where_clauses[] = "vki BETWEEN 18.5 AND 24.9";
    elseif ($vki_filtre == 'Overweight') $where_clauses[] = "vki BETWEEN 25 AND 29.9";
    elseif ($vki_filtre == 'Obese') $where_clauses[] = "vki >= 30";
    elseif ($vki_filtre == 'Underweight') $where_clauses[] = "vki < 18.5";
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

function executeQuery($db, $sql, $types = "", $params = []) {
    $stmt = $db->prepare($sql);
    if(!$stmt){ die("SQL Hatası: " . $db->error); }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}

// --- 4. VERİLERİ GÜVENLİ SORGULARLA ÇEKME ---
$result = executeQuery($baglanti, "SELECT COUNT(*) as toplam" . $base_query . $where_sql, $types, $params);
$toplam_hasta = $result->fetch_assoc()['toplam'] ?? 0;

function getAverage($db, $column, $base_query, $where_sql, $types, $params) {
    $result = executeQuery($db, "SELECT AVG($column) as ortalama" . $base_query . $where_sql, $types, $params);
    return $result->fetch_assoc()['ortalama'] ?? 0;
}

$ortalama_vki = getAverage($baglanti, 'vki', $base_query, $where_sql, $types, $params);
$ortalama_glikoz = getAverage($baglanti, 'glikoz', $base_query, $where_sql, $types, $params);
$ortalama_gebelik = getAverage($baglanti, 'gebelik_sayisi', $base_query, $where_sql, $types, $params);
$ortalama_cilt_kalinligi = getAverage($baglanti, 'deri_kalinligi', $base_query, $where_sql, $types, $params);
$count_of_pregnancies_stat = $toplam_hasta;

// --- RİSK HESAPLAMA ---
$query_risk_counts = "SELECT SUM(CASE WHEN glikoz > 140 AND vki > 30 THEN 1 ELSE 0 END) as yuksek_risk, SUM(CASE WHEN (glikoz > 140 AND vki > 30) = 0 AND ((glikoz BETWEEN 100 AND 140) OR (vki BETWEEN 25 AND 29.9)) THEN 1 ELSE 0 END) as orta_risk, SUM(CASE WHEN (glikoz > 140 AND vki > 30) = 0 AND ((glikoz BETWEEN 100 AND 140) OR (vki BETWEEN 25 AND 29.9)) = 0 THEN 1 ELSE 0 END) as dusuk_risk" . $base_query . $where_sql;
$result_risk_counts = executeQuery($baglanti, $query_risk_counts, $types, $params);
$risk_counts = $result_risk_counts->fetch_assoc();

$risk_yuksek_count = (int)($risk_counts['yuksek_risk'] ?? 0);
$risk_orta_count = (int)($risk_counts['orta_risk'] ?? 0);
$risk_dusuk_count = (int)($risk_counts['dusuk_risk'] ?? 0);

$risk_kategorileri_pie_3cat = ["Yüksek", "Orta", "Düşük"];
$risk_sayilari_pie_3cat = [$risk_yuksek_count, $risk_orta_count, $risk_dusuk_count];
$toplam_risk_pie_3cat = array_sum($risk_sayilari_pie_3cat);
$risk_yuksek_yuzde_pie_3cat = ($toplam_risk_pie_3cat > 0) ? ($risk_yuksek_count / $toplam_risk_pie_3cat) * 100 : 0;
$risk_orta_yuzde_pie_3cat = ($toplam_risk_pie_3cat > 0) ? ($risk_orta_count / $toplam_risk_pie_3cat) * 100 : 0;
$risk_dusuk_yuzde_pie_3cat = ($toplam_risk_pie_3cat > 0) ? ($risk_dusuk_count / $toplam_risk_pie_3cat) * 100 : 0;

// GAUGE GRAFİĞİ İÇİN VERİLER
$gauge_max_value = 40.0;
$yuksek_risk_yuzdesi_gauge = ($toplam_hasta > 0) ? ($risk_yuksek_count / $toplam_hasta) * 100 : 0;

if ($yuksek_risk_yuzdesi_gauge > 25) { $gauge_text_label = "Yüksek"; $gauge_arc_color = '#e74c3c'; } 
elseif ($yuksek_risk_yuzdesi_gauge > 15) { $gauge_text_label = "Orta"; $gauge_arc_color = '#f39c12'; } 
else { $gauge_text_label = "Düşük"; $gauge_arc_color = '#2ecc71'; }

// DİĞER GRAFİKLERİN VERİLERİ
$yaslar_glikoz_grafik = []; $glikozlar_grafik_deger = [];
$result_yas_glikoz = executeQuery($baglanti, "SELECT yas, AVG(glikoz) AS avg_glucose" . $base_query . $where_sql . " GROUP BY yas ORDER BY yas", $types, $params);
if ($result_yas_glikoz) { while ($row = $result_yas_glikoz->fetch_assoc()) { $yaslar_glikoz_grafik[] = $row['yas']; $glikozlar_grafik_deger[] = round($row['avg_glucose'],2); }}

$query_vki_dagilim = "SELECT SUM(CASE WHEN vki < 18.5 THEN 1 ELSE 0 END) AS zayif, SUM(CASE WHEN vki >= 18.5 AND vki <= 24.9 THEN 1 ELSE 0 END) AS normal, SUM(CASE WHEN vki >= 25 AND vki <= 29.9 THEN 1 ELSE 0 END) AS fazla_kilolu, SUM(CASE WHEN vki >= 30 THEN 1 ELSE 0 END) AS obez" . $base_query . $where_sql;
$result_vki_dagilim = executeQuery($baglanti, $query_vki_dagilim, $types, $params);
$vki_counts = $result_vki_dagilim->fetch_assoc();
$vki_kategorileri_chart_names = ["Obez", "Fazla Kilolu", "Normal", "Zayıf"];
$vki_sayilar_chart_values = [$vki_counts['obez'] ?? 0, $vki_counts['fazla_kilolu'] ?? 0, $vki_counts['normal'] ?? 0, $vki_counts['zayif'] ?? 0];

$yas_vki_kan_basinci_data = [];
$vki_kategorileri_tablo_order = ["Normal", "Obese", "Overweight", "Underweight"];
$yas_kategorileri_tablo_order = ["30-50", "High Age", "Less Than 30"];
$yas_kategorileri_gosterim_turkce = ["30-50 Yaş Arası", "50 Yaş Üstü", "30 Yaş Altı"];
$vki_kategorileri_gosterim_turkce = ["Normal", "Obez", "Fazla Kilolu", "Zayıf"];
$query_tablo_bp = "SELECT (CASE WHEN yas < 30 THEN 'Less Than 30' WHEN yas >= 30 AND yas <= 50 THEN '30-50' ELSE 'High Age' END) AS age_cat, (CASE WHEN vki < 18.5 THEN 'Underweight' WHEN vki >= 18.5 AND vki <= 24.9 THEN 'Normal' WHEN vki >= 25 AND vki <= 29.9 THEN 'Overweight' ELSE 'Obese' END) AS bmi_cat, AVG(kan_basinci) as avg_bp" . $base_query . $where_sql . " GROUP BY age_cat, bmi_cat";
$result_tablo_bp = executeQuery($baglanti, $query_tablo_bp, $types, $params);
$temp_tablo_verisi = [];
if($result_tablo_bp){ while($row = $result_tablo_bp->fetch_assoc()) { $temp_tablo_verisi[$row['age_cat']][$row['bmi_cat']] = round($row['avg_bp'], 2); }}
foreach ($yas_kategorileri_tablo_order as $yas_kat) {
    $yas_vki_kan_basinci_data[$yas_kat] = [];
    foreach ($vki_kategorileri_tablo_order as $vki_kat) { $yas_vki_kan_basinci_data[$yas_kat][$vki_kat] = $temp_tablo_verisi[$yas_kat][$vki_kat] ?? '0.00'; }
    $age_condition_row_total = "";
    if ($yas_kat == 'Less Than 30') $age_condition_row_total = " AND yas < 30"; elseif ($yas_kat == '30-50') $age_condition_row_total = " AND yas BETWEEN 30 AND 50"; elseif ($yas_kat == 'High Age') $age_condition_row_total = " AND yas > 50";
    $result_row_total_bp = executeQuery($baglanti, "SELECT AVG(kan_basinci) as avg_bp" . $base_query . $where_sql . $age_condition_row_total, $types, $params);
    $yas_vki_kan_basinci_data[$yas_kat]['Total'] = $result_row_total_bp ? round($result_row_total_bp->fetch_assoc()['avg_bp'] ?? 0, 2) : '0.00';
}
$tablo_genel_toplamlar = [];
foreach ($vki_kategorileri_tablo_order as $vki_kat) {
    $bmi_condition_col_total = "";
    if ($vki_kat == 'Normal') $bmi_condition_col_total = " AND vki BETWEEN 18.5 AND 24.9"; elseif ($vki_kat == 'Overweight') $bmi_condition_col_total = " AND vki BETWEEN 25 AND 29.9"; elseif ($vki_kat == 'Obese') $bmi_condition_col_total = " AND vki >= 30"; elseif ($vki_kat == 'Underweight') $bmi_condition_col_total = " AND vki < 18.5";
    $result_col_total_bp = executeQuery($baglanti, "SELECT AVG(kan_basinci) as avg_bp" . $base_query . $where_sql . $bmi_condition_col_total, $types, $params);
    $tablo_genel_toplamlar[$vki_kat] = $result_col_total_bp ? round($result_col_total_bp->fetch_assoc()['avg_bp'] ?? 0, 2) : '0.00';
}
$result_grand_total_bp = executeQuery($baglanti, "SELECT AVG(kan_basinci) as avg_bp" . $base_query . $where_sql, $types, $params);
$tablo_genel_toplamlar['Total'] = $result_grand_total_bp ? round($result_grand_total_bp->fetch_assoc()['avg_bp'] ?? 0, 2) : '0.00';

$yas_kan_basinci_etiketler = []; $yas_kan_basinci_degerler = [];
$query_yas_bp = "SELECT FLOOR(yas/10)*10 as age_group, AVG(kan_basinci) AS avg_bp" . $base_query . $where_sql . " GROUP BY age_group ORDER BY age_group";
$result_yas_bp = executeQuery($baglanti, $query_yas_bp, $types, $params);
if ($result_yas_bp) { while ($row = $result_yas_bp->fetch_assoc()) { $yas_kan_basinci_etiketler[] = $row['age_group'] . "-" . ($row['age_group']+9); $yas_kan_basinci_degerler[] = round($row['avg_bp'],2); }}

$baglanti->close();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Uluslararası FuAy Hastanesi - Diyabet Analiz Paneli</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
            font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #495057;
            padding-top: 15px;
            padding-bottom: 15px;
        }
        .dashboard-header {
            background-color: #ffffff; padding: 12px 20px; border-radius: 6px; margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04); display: flex; align-items: center; justify-content: space-between;
        }
        .dashboard-header .logo-title { display: flex; align-items: center; gap: 12px; }
        .dashboard-header .logo {
            width: 36px; height: 36px; background-color: #c0392b; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 1.1rem;
        }
        .dashboard-header h1 { font-size: 1.4rem; color: #343a40; margin: 0; font-weight: 500; }
        .filter-controls { display: flex; align-items: center; gap: 8px; }
        .form-control-sm { font-size: 0.8rem; height: auto; }

        .stat-card {
            background-color: #ffffff; padding: 12px; border-radius: 6px; margin-bottom: 20px;
            text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            border: 1px solid #e9ecef;
        }
        .stat-card h5 { font-size: 0.75rem; color: #6c757d; margin-bottom: 4px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;}
        .stat-card p { font-size: 1.5rem; color: #212529; font-weight: 600; margin-bottom: 0; }

        .chart-card {
            background-color: #ffffff; padding: 0; border-radius: 6px; margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            border: 1px solid #e9ecef;
        }
        .chart-card-header {
            background-color: #495057; color: #ffffff; padding: 10px 15px;
            border-top-left-radius: 5px; border-top-right-radius: 5px;
            font-size: 0.9rem; font-weight: 500; border-bottom: 1px solid #343a40;
        }
        .chart-card-body {
            padding: 15px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        /* Genel canvas kuralı (min-height kaldırıldı) */
        .chart-card-body canvas {
            width: 100% !important;
            max-height: 250px; /* Çok büyümelerini engeller */
        }
        #riskPieChart, #gaugeRiskChart {
            height: 180px !important;
            min-height: 180px !important; /* Önceki kuralı ezmek için */
        }

        /* Risk dağılımı grafiğinin altındaki metinler için ayar */
        .risk-pie-legend {
            margin-bottom: 10px; font-size: 0.75rem; display: flex;
            justify-content: center; flex-wrap: wrap; gap: 10px;
        }

        /* Tablo Stilleri */
        .table-container { max-height: 300px; overflow-y: auto; }
        .table thead th {
            background-color: #495057; color: white; font-size: 0.75rem; font-weight: 500;
            position: sticky; top: 0; z-index: 10;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <form method="GET" action="" id="filterFormDashboard">
            <div class="dashboard-header">
                <div class="logo-title"> <div class="logo">FA</div> <h1>Uluslararası FuAy Hastanesi - Diyabet Analiz Paneli</h1> </div>
                <div class="filter-controls">
                    <select class="form-control form-control-sm" name="yas" onchange="this.form.submit()"> <option value="" <?= $yas_filtre == '' ? 'selected' : '' ?>>Yaş (Tümü)</option> <option value="Less Than 30" <?= $yas_filtre == 'Less Than 30' ? 'selected' : '' ?>>30 Yaş Altı</option> <option value="30-50" <?= $yas_filtre == '30-50' ? 'selected' : '' ?>>30-50 Yaş</option> <option value="High Age" <?= $yas_filtre == 'High Age' ? 'selected' : '' ?>>50 Yaş Üstü</option> </select>
                    <select class="form-control form-control-sm" name="vki" onchange="this.form.submit()"> <option value="" <?= $vki_filtre == '' ? 'selected' : '' ?>>VKİ (Tümü)</option> <option value="Underweight" <?= $vki_filtre == 'Underweight' ? 'selected' : '' ?>>Zayıf</option> <option value="Normal" <?= $vki_filtre == 'Normal' ? 'selected' : '' ?>>Normal</option> <option value="Overweight" <?= $vki_filtre == 'Overweight' ? 'selected' : '' ?>>Fazla Kilolu</option> <option value="Obese" <?= $vki_filtre == 'Obese' ? 'selected' : '' ?>>Obez</option> </select>
                    <input type="hidden" name="gebelik_max" id="gebelikMaxHiddenInput" value="<?= htmlspecialchars($gebelik_max_filtre) ?>">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="resetFiltersBtn" title="Filtreleri Sıfırla"> <i class="fas fa-sync-alt"></i> Sıfırla </button>
                </div>
            </div>
        </form>

        <!-- ÜST İSTATİSTİK KARTLARI -->
        <div class="row">
            <div class="col-lg-2 col-md-4 col-6 mb-4"><div class="stat-card"><h5><i class="fas fa-users mr-1"></i>TOPLAM HASTA</h5><p><?= number_format($toplam_hasta) ?></p></div></div>
            <div class="col-lg-2 col-md-4 col-6 mb-4"><div class="stat-card"><h5><i class="fas fa-weight mr-1"></i>ORT. VKİ</h5><p><?= number_format($ortalama_vki, 2) ?></p></div></div>
            <div class="col-lg-2 col-md-4 col-6 mb-4"><div class="stat-card"><h5><i class="fas fa-tint mr-1"></i>ORT. GLİKOZ</h5><p><?= number_format($ortalama_glikoz, 2) ?></p></div></div>
            <div class="col-lg-2 col-md-4 col-6 mb-4"><div class="stat-card"><h5><i class="fas fa-baby mr-1"></i>ORT. GEBELİK</h5><p><?= number_format($ortalama_gebelik, 2) ?></p></div></div>
            <div class="col-lg-2 col-md-4 col-6 mb-4"><div class="stat-card"><h5><i class="fas fa-female mr-1"></i>GEBELİK SAYISI</h5><p><?= number_format($count_of_pregnancies_stat) ?></p></div></div>
            <div class="col-lg-2 col-md-4 col-6 mb-4"><div class="stat-card"><h5><i class="fas fa-ruler-combined mr-1"></i>ORT. DERİ KALINLIĞI</h5><p><?= number_format($ortalama_cilt_kalinligi, 2) ?></p></div></div>
        </div>

        <!-- ANA İÇERİK BÖLÜMÜ -->
        <div class="row">
            <!-- Sol Sütun (Filtre ve Risk) -->
            <div class="col-lg-3">
                <div class="chart-card">
                    <div class="chart-card-header">Gebelik Aralığı</div>
                    <div class="chart-card-body">
                         <input type="range" class="form-control-range" id="pregnanciesSingleMaxSlider" min="<?= $pregnancies_min_general ?>" max="<?= $pregnancies_max_general ?>" value="<?= htmlspecialchars($gebelik_max_filtre) ?>">
                        <div class="text-center mt-1"><small>Değer: <span id="currentMaxPregVal"><?= htmlspecialchars($gebelik_max_filtre) ?></span></small></div>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-card-header">Diyabet Riski Dağılımı</div>
                    <div class="chart-card-body text-center">
                        <canvas id="riskPieChart" style="max-height: 180px;"></canvas>
                        <div class="risk-pie-legend">
                            <span style="color:#e74c3c;"><i class="fas fa-circle fa-xs"></i> Yüksek</span>
                            <span style="color:#f39c12;"><i class="fas fa-circle fa-xs"></i> Orta</span>
                            <span style="color:#2ecc71;"><i class="fas fa-circle fa-xs"></i> Düşük</span>
                        </div>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-card-header">Diyabet Risk Yüzdesi</div>
                    <div class="chart-card-body">
                        <canvas id="gaugeRiskChart" style="max-height: 180px;"></canvas>
                    </div>
                </div>
            </div>

            <!-- Orta Sütun (Ana Grafikler) -->
            <div class="col-lg-6">
                <div class="chart-card">
                    <div class="chart-card-header">Yaşa Göre Ortalama Glikoz</div>
                    <div class="chart-card-body"><canvas id="glucoseByAgeChart" style="height: 400px;"></canvas></div>
                </div>
                <div class="chart-card">
                    <div class="chart-card-header">Yaş ve VKİ'ye Göre Kan Basıncı</div>
                    <div class="chart-card-body p-0">
                        <div class="table-container">
                            <table class="table table-striped table-hover table-sm mb-0">
                                <thead><tr><th>Yaş Kategorisi</th><?php foreach($vki_kategorileri_gosterim_turkce as $kategori_tr):?><th><?= htmlspecialchars($kategori_tr) ?></th><?php endforeach;?><th>Toplam</th></tr></thead>
                                <tbody><?php $ordered_yas_vki_data = []; foreach($yas_kategorileri_tablo_order as $idx => $yas_kat_key) { $ordered_yas_vki_data[$yas_kat_key] = [ 'label' => $yas_kategorileri_gosterim_turkce[$idx] ?? $yas_kat_key, 'data' => $yas_vki_kan_basinci_data[$yas_kat_key] ?? [] ]; } foreach ($ordered_yas_vki_data as $yas_kat_key => $yas_info): ?><tr><td><b><?= htmlspecialchars($yas_info['label']) ?></b></td><?php foreach ($vki_kategorileri_tablo_order as $vki_kat_key): ?><td><?= $yas_info['data'][$vki_kat_key] ?? '0.00' ?></td><?php endforeach;?><td><?= $yas_info['data']['Total'] ?? '0.00' ?></td></tr><?php endforeach;?><tr><td><b>Toplam</b></td><?php foreach($vki_kategorileri_tablo_order as $vki_kat_key): ?><td><b><?= $tablo_genel_toplamlar[$vki_kat_key] ?? '0.00' ?></b></td><?php endforeach;?><td><b><?= $tablo_genel_toplamlar['Total'] ?? '0.00' ?></b></td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sağ Sütun (Yardımcı Grafikler) -->
            <div class="col-lg-3">
                 <div class="chart-card">
                    <div class="chart-card-header">VKİ Kategorisine Göre Hasta Sayısı</div>
                    <div class="chart-card-body"><canvas id="bmiByCategoryChart"></canvas></div>
                </div>
                 <div class="chart-card">
                    <div class="chart-card-header">Yaşa Göre Kan Basıncı</div>
                    <div class="chart-card-body"><canvas id="bloodPressureByAgeChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>
    <script>
        const primaryColor = '#3498db'; const secondaryColor = '#2ecc71'; const tertiaryColor = '#e74c3c';
        const warningColor = '#f39c12'; const infoColor = '#9b59b6'; const darkTextColor = '#34495e';
        const lightGridColor = 'rgba(0,0,0,0.05)';

        Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
        Chart.defaults.color = '#555';

        const riskPieCtx = document.getElementById('riskPieChart');
        if (riskPieCtx) {
            new Chart(riskPieCtx.getContext('2d'), { type: 'doughnut', data: { labels: <?= json_encode($risk_kategorileri_pie_3cat) ?>, datasets: [{ data: <?= json_encode($risk_sayilari_pie_3cat) ?>, backgroundColor: [tertiaryColor, warningColor, secondaryColor], borderColor: '#ffffff', borderWidth: 2, hoverOffset: 4 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(c){ let l=c.label||''; if(l){l+=': '} const v=c.raw; const t=c.dataset.data.reduce((a,b)=>a+b,0); const p=t>0?(v/t*100).toFixed(2)+'%':'0%'; return l+v+' ('+p+')'; }}}} } });
        }
        
        // GAUGE CHART EKLENECEK
        const gaugeRiskCtx = document.getElementById('gaugeRiskChart');
        if (gaugeRiskCtx) {
            const gaugeTextPlugin = {
                id: 'gaugeTextPlugin',
                beforeDraw: (chart) => {
                    const { ctx, chartArea: { top, bottom, left, right, width, height} } = chart;
                    ctx.save();
                    const x = width / 2;
                    const y = top + (height * 0.70);

                    ctx.fillStyle = chart.data.datasets[0].backgroundColor[0];
                    ctx.font = 'bold 1.6rem "Segoe UI", sans-serif';
                    ctx.textAlign = 'center';
                    ctx.fillText('<?= number_format($yuksek_risk_yuzdesi_gauge, 2) ?>%', x, y);

                    ctx.font = '0.8rem "Segoe UI", sans-serif';
                    ctx.fillStyle = '#555';
                    ctx.fillText('<?= htmlspecialchars($gauge_text_label) ?> Risk', x, y + 25);
                    ctx.restore();
                }
            };

            new Chart(gaugeRiskCtx, {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [
                            <?= $yuksek_risk_yuzdesi_gauge ?>, 
                            <?= max(0, $gauge_max_value - $yuksek_risk_yuzdesi_gauge) ?>
                        ],
                        backgroundColor: [
                            '<?= $gauge_arc_color ?>',
                            '#e9ecef'
                        ],
                        borderWidth: 0,
                        cutout: '80%'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    circumference: 180,
                    rotation: -90,
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: false }
                    }
                },
                plugins: [gaugeTextPlugin]
            });
        }

        const glucoseByAgeCtx = document.getElementById('glucoseByAgeChart');
        if (glucoseByAgeCtx) {
            new Chart(glucoseByAgeCtx.getContext('2d'), { type: 'line', data: { labels: <?= json_encode($yaslar_glikoz_grafik) ?>, datasets: [{ label: 'Ortalama Glikoz', data: <?= json_encode($glikozlar_grafik_deger) ?>, borderColor: primaryColor, backgroundColor: 'rgba(52, 152, 219, 0.1)', fill: true, tension: 0.3, pointBackgroundColor: primaryColor, pointRadius: 3, pointHoverRadius: 5 }] }, options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: false, grid: { color: lightGridColor }, ticks: { color: darkTextColor } }, x: { title: { display: true, text: 'Yaş', color: darkTextColor }, grid: { display: false }, ticks: { color: darkTextColor } } }, plugins: { legend: { display: false }, tooltip: { backgroundColor: 'rgba(0,0,0,0.7)', titleColor: '#fff', bodyColor: '#fff' } } } });
        }
        const bmiByCategoryCtx = document.getElementById('bmiByCategoryChart');
        if (bmiByCategoryCtx) {
            new Chart(bmiByCategoryCtx.getContext('2d'), { type: 'pie', data: { labels: <?= json_encode($vki_kategorileri_chart_names) ?>, datasets: [{ data: <?= json_encode($vki_sayilar_chart_values) ?>, backgroundColor: [tertiaryColor, warningColor, primaryColor, infoColor], borderColor: '#ffffff', borderWidth: 2, hoverOffset: 4 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top', labels: { color: darkTextColor, boxWidth: 12, padding: 15 } }, tooltip: { callbacks: { label: function(c){ let l=c.label||''; if(l){l+=': '} const v=c.raw; const t=c.dataset.data.reduce((a,b)=>a+b,0); const p=t>0?(v/t*100).toFixed(2)+'%':'0%'; return l+v+' ('+p+')'; }}}} } });
        }
        const bloodPressureByAgeCtx = document.getElementById('bloodPressureByAgeChart');
        if (bloodPressureByAgeCtx) {
            new Chart(bloodPressureByAgeCtx.getContext('2d'), { type: 'bar', data: { labels: <?= json_encode($yas_kan_basinci_etiketler) ?>, datasets: [{ label: 'Ortalama Kan Basıncı', data: <?= json_encode($yas_kan_basinci_degerler) ?>, backgroundColor: primaryColor, borderRadius: 4, barPercentage: 0.7 }] }, options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', scales: { y: { grid: { display: false }, ticks: { color: darkTextColor } }, x: { title: { display: true, text: 'Ortalama Kan Basıncı', color: darkTextColor}, grid: { color: lightGridColor }, ticks: { color: darkTextColor } } }, plugins: { legend: { display: false } } } });
        }

        const pregnanciesSingleMaxSlider = document.getElementById('pregnanciesSingleMaxSlider');
        const currentMaxPregVal = document.getElementById('currentMaxPregVal');
        const gebelikMaxHiddenInput = document.getElementById('gebelikMaxHiddenInput');
        if(pregnanciesSingleMaxSlider && currentMaxPregVal && gebelikMaxHiddenInput){
            pregnanciesSingleMaxSlider.addEventListener('input', function() { currentMaxPregVal.textContent = this.value; });
            pregnanciesSingleMaxSlider.addEventListener('change', function() { gebelikMaxHiddenInput.value = this.value; document.getElementById('filterFormDashboard').submit(); });
        }
        document.getElementById('resetFiltersBtn').addEventListener('click', function() {
            const form = document.getElementById('filterFormDashboard');
            form.querySelector('select[name="yas"]').value = ''; 
            form.querySelector('select[name="vki"]').value = '';
            const defaultMaxPreg = <?= $pregnancies_max_general ?>;
            if(pregnanciesSingleMaxSlider) pregnanciesSingleMaxSlider.value = defaultMaxPreg;
            if(currentMaxPregVal) currentMaxPregVal.textContent = defaultMaxPreg;
            if(gebelikMaxHiddenInput) gebelikMaxHiddenInput.value = defaultMaxPreg;
            form.submit();
        });
    </script>
</body>
</html>