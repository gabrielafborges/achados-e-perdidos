<?php

// GERENCIAMENTO DE FILA REDIS (ADMIN_FILA.PHP)
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../config/db.php';

$redis = Database::getRedis();
$msgFila = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_pop_fila'])) {
    if ($redis) {
        try {
            $poppedItemId = $redis->rPop("fila:reivindicacoes_pendentes");
            if ($poppedItemId) {
                updateMongoDocument('itens', ['_id' => new MongoDB\BSON\ObjectId($poppedItemId)], [
                    '$set' => ['status' => 'devolvido'],
                    '$push' => [
                        'historico_status' => [
                            'data' => new MongoDB\BSON\UTCDateTime(),
                            'status' => 'devolvido',
                            'usuario_id' => new MongoDB\BSON\ObjectId('65c100000000000000000001'),
                            'observacao' => 'Devolução confirmada via Fila de Atendimento do Redis.'
                        ]
                    ]
                ]);
                $msgFila = "Item <code>{$poppedItemId}</code> removido da Fila do Redis e marcado como DEVOLVIDO no MongoDB.";
            } else {
                $msgFila = "A Fila do Redis está vazia no momento.";
            }
        } catch (Exception $e) {}
    }
}

$filaItemsRaw = [];
if ($redis) {
    try {
        $filaItemsRaw = $redis->lRange("fila:reivindicacoes_pendentes", 0, -1);
    } catch (Exception $e) {}
}

$filaItems = [];
foreach ($filaItemsRaw as $idStr) {
    $doc = getMongoDocumentById('itens', $idStr);
    if ($doc) {
        $filaItems[] = [
            'id' => (string)$doc->_id,
            'titulo' => $doc->titulo ?? '',
            'categoria' => $doc->categoria ?? '',
            'justificativa' => $doc->reivindicao->justificativa ?? 'Sem justificativa',
            'data_reivindicacao' => isset($doc->reivindicao->data_reivindicacao) ? date('d/m/Y H:i', $doc->reivindicao->data_reivindicacao->toDateTime()->getTimestamp()) : 'Recente'
        ];
    }
}

$onlineUsers = [];
if ($redis) {
    try {
        $onlineUsers = $redis->sMembers("online:usuarios");
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fila de Reivindicações | Achados e Perdidos IFMG</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/public/css/style.css">
</head>
<body>

    <header class="header-nav">
        <div class="nav-container">
            <a href="index.php" class="logo-group">
                <img src="https://www.ifmg.edu.br/portal/imagens/logovertical.jpg" alt="Logo IFMG" class="logo-img">
                <div class="logo-text">Achados e Perdidos</div>
            </a>
            <ul class="nav-links">
                <li><a href="index.php" class="nav-link">Voltar ao Catálogo</a></li>
            </ul>
        </div>
    </header>

    <div class="container">

        <?php if (!empty($msgFila)): ?>
            <div class="cache-banner hit" style="margin-bottom: 2rem;">
                <?= $msgFila ?>
            </div>
        <?php endif; ?>

        <div class="layout-grid">

            <!-- Fila de Atendimento List -->
            <div>
                <div class="hero-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h2 style="font-family: var(--font-heading); font-size: 1.5rem; color: white;">Fila de Reivindicações Pendentes</h2>
                            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.3rem;">
                                Estrutura do tipo List no Redis sob a chave <code>fila:reivindicacoes_pendentes</code> onde as solicitações entram por ordem de chegada (FIFO).
                            </p>
                        </div>
                        <?php if (!empty($filaItems)): ?>
                            <form method="POST">
                                <button type="submit" name="action_pop_fila" class="btn-primary">
                                    Processar Próximo da Fila (RPOP)
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($filaItems)): ?>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($filaItems as $idx => $item): ?>
                                <div style="background: rgba(5, 12, 8, 0.7); padding: 1.2rem; border-radius: 14px; border: 1px solid var(--border-glass); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                                    <div>
                                        <div style="font-size: 0.75rem; color: var(--neon-green); font-weight: 800;">POSIÇÃO #<?= $idx + 1 ?> NA FILA</div>
                                        <h3 style="font-family: var(--font-heading); font-size: 1.15rem; color: white; margin: 0.2rem 0;"><?= htmlspecialchars($item['titulo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
                                        <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.25rem;">
                                            <strong>Justificativa:</strong> "<?= htmlspecialchars($item['justificativa'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                        </p>
                                        <small style="color: var(--text-muted);">Solicitado em: <?= $item['data_reivindicacao'] ?></small>
                                    </div>
                                    <a href="item_detalhe.php?id=<?= $item['id'] ?>" class="btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">Inspecionar Item</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            Nenhuma reivindicação pendente na Fila do Redis no momento.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar: Redis Set & Hashes Info -->
            <div>
                <div class="sidebar-panel">
                    <h3 class="panel-title">Usuários Online:</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                        Chave Set do Redis: <code>online:usuarios</code>
                    </p>
                    <ul class="ranking-list">
                        <?php foreach ($onlineUsers as $user): ?>
                            <li class="ranking-item">
                                <span style="font-size: 0.85rem; font-family: var(--font-mono); color: var(--neon-green);"><?= htmlspecialchars($user, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="sidebar-panel">
                    <h3 class="panel-title">Padrão de Chaves Redis</h3>
                    <ul style="font-size: 0.8rem; color: var(--text-secondary); line-height: 1.8; list-style: square; padding-left: 1.2rem;">
                        <li><code>sessao:usuario:{id}</code> (String, TTL 3600s)</li>
                        <li><code>cache:item:{id}</code> (String, TTL 300s)</li>
                        <li><code>resumo:item:{id}</code> (Hash)</li>
                        <li><code>ranking:locais_perdas</code> (Sorted Set)</li>
                        <li><code>fila:reivindicacoes_pendentes</code> (List)</li>
                        <li><code>online:usuarios</code> (Set)</li>
                    </ul>
                </div>
            </div>

        </div>

    </div>

</body>
</html>
