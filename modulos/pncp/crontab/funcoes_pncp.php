<?php
// funcoes_pncp.php
// Funções utilitárias para integração com PNCP
// Substitua / adapte conforme sua estrutura — o arquivo foi escrito para ser "drop-in".

// Segurança: timezone
if (!ini_get('date.timezone')) {
  date_default_timezone_set('America/Sao_Paulo');
}

// Se logar() não existir (cron às vezes já define), definimos um fallback que grava no mesmo arquivo.
/*if (!function_exists('logar')) {
  function logar($mensagem)
  {
    // tenta escrever no mesmo caminho esperado pelos crons
    $logFile = __DIR__ . '/logs/cron_full_cache.log';
    $linha = "[" . date('Y-m-d H:i:s') . "] " . $mensagem . PHP_EOL;
    // ecoa para stdout (útil ao rodar CLI)
    echo $linha;
    // garante diretório
    $dir = dirname($logFile);
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    @file_put_contents($logFile, $linha, FILE_APPEND);
  }
}*/

// Registrar falha simples
function registrarFalha(PDO $pdo, string $numeroControlePNCP, string $motivo, int $tentativas = 0)
{
  try {
    $st = $pdo->prepare("INSERT INTO cache_pncp_falhas
            (numeroControlePNCP, motivo, tentativas, status, criado_em)
            VALUES (?, ?, ?, 'pendente', NOW())");
    $st->execute([$numeroControlePNCP, $motivo, $tentativas]);
  } catch (Throwable $e) {
    // não interromper o fluxo, apenas log local
    logar("⚠️ registrarFalha erro: " . $e->getMessage());
  }
}

/**
 * consultarApi
 * - Retorna array (json) em caso de 200
 * - Retorna null em caso de resposta HTTP 400 (indicando janela inválida / params)
 * - Retorna false em caso de erro de cURL / timeout (para re-tentativa externa)
 */
function consultarApi(string $url, int $maxTentativas = 4)
{
  $uas = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 13_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16 Safari/605.1.15"
  ];

  for ($tent = 1; $tent <= $maxTentativas; $tent++) {
    $ua = $uas[array_rand($uas)];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_MAXREDIRS      => 5,
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_TIMEOUT        => 45,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_ENCODING       => "",
      CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
      CURLOPT_HTTPHEADER     => [
        "Accept: application/json",
        "User-Agent: $ua"
      ],
    ]);

    $res = curl_exec($ch);
    $err = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
      logar("⚠️ cURL tent.$tent: $err");
      // continue para retry
    } else {
      if ($status === 200) {
        $json = json_decode($res, true);
        if (is_array($json)) return $json;
        logar("⚠️ Resposta não-JSON tent.$tent: " . substr($res, 0, 300));
      } elseif ($status === 400) {
        // bad request → retornar null (janela inválida)
        logar("⚠️ HTTP 400 (Bad Request) — ignorando params: $url");
        return null;
      } elseif (in_array($status, [429, 502, 503])) {
        logar("⚠️ HTTP $status — retry tent.$tent");
      } elseif ($status === 204) {
        // sem conteúdo - tratar como sucesso mas sem data
        logar("ℹ️ HTTP 204 (No Content) — $url");
        return ['data' => []];
      } else {
        logar("⚠️ HTTP $status — " . substr($res, 0, 300));
      }
    }

    // backoff com jitter
    $sleep = min(8, 2 ** $tent) + mt_rand(0, 1000) / 1000;
    usleep((int)($sleep * 1_000_000));
  }

  return false;
}

/**
 * baixarJson - wrapper simples
 * Tenta a consulta e retorna o 'data' quando apropriado.
 */
function baixarJson(string $url)
{
  $r = consultarApi($url);
  if ($r === false) return false;
  if ($r === null) return null;
  // alguns endpoints retornam array diretamente, outros retornam ['data'=>[...] ]
  if (isset($r['data'])) return $r['data'];
  return $r;
}

/**
 * salvarProcesso - insere/atualiza processo na tabela cache_pncp_processos
 * Mantém redundância de colunas para facilitar busca (modalidade_id, modalidade_nome, situacao_nome, orgao, uf, dataPublicacao)
 */

function salvarProcesso(PDO $pdo, array $proc): bool
{
  if (empty($proc['numeroControlePNCP'])) {
    logar("⚠️ Processo sem numeroControlePNCP — ignorado");
    return false;
  }

  $numero = $proc['numeroControlePNCP'];

  $sql = "INSERT INTO cache_pncp_processos
          (numeroControlePNCP, objeto, orgao, uf, modalidade, status, dataPublicacao, dataAbertura, dataEncerramento, json_original)
        VALUES
          (:numeroControlePNCP, :objeto, :orgao, :uf, :modalidade, :status, :dataPublicacao, :dataAbertura, :dataEncerramento, :json)
        ON DUPLICATE KEY UPDATE
          objeto = VALUES(objeto),
          orgao = VALUES(orgao),
          uf = VALUES(uf),
          modalidade = VALUES(modalidade),
          status = VALUES(status),
          dataPublicacao = VALUES(dataPublicacao),
          dataAbertura = VALUES(dataAbertura),
          dataEncerramento = VALUES(dataEncerramento),
          json_original = VALUES(json_original),
          atualizado_em = CURRENT_TIMESTAMP";

  $stmt = $pdo->prepare($sql);

  $ok = $stmt->execute([
    ':num'     => $numero,
    ':obj'     => $proc['objetoCompra'] ?? '',
    ':orgao'   => $proc['orgaoEntidade']['razaoSocial'] ?? '',
    ':uf'      => $proc['unidadeOrgao']['ufSigla'] ?? '',
    ':status'  => $proc['situacaoCompraNome'] ?? '',
    ':dPub'    => !empty($proc['dataPublicacaoPncp']) ? substr($proc['dataPublicacaoPncp'], 0, 10) : null,
    ':dAbe'    => !empty($proc['dataAberturaProposta']) ? substr($proc['dataAberturaProposta'], 0, 10) : null,
    ':dEnc'    => !empty($proc['dataEncerramentoProposta']) ? substr($proc['dataEncerramentoProposta'], 0, 10) : null,
    ':modId'   => $proc['modalidadeId'] ?? null,
    ':modNome' => $proc['modalidadeNome'] ?? null,
    ':sitNome' => $proc['situacaoCompraNome'] ?? null,
    ':dPub2'   => !empty($proc['dataPublicacaoPncp']) ? substr($proc['dataPublicacaoPncp'], 0, 10) : null,
    ':json'    => json_encode($proc, JSON_UNESCAPED_UNICODE)
  ]);

  if ($ok) {
    logar("💾 Processo $numero salvo/atualizado");
    return true;
  } else {
    logar("❌ Falha ao salvar processo $numero");
    return false;
  }
}

/**
 * salvarItensDoProcesso - utiliza endpoint PNCP paginado (estrutura /pncp/v1/orgaos/{cnpj}/compras/{ano}/{seq}/itens)
 * - remove itens antigos antes de inserir
 * - registra falhas quando não encontra
 */
function salvarItensDoProcesso(PDO $pdo, string $numeroControlePNCP): bool
{
  [$cnpj, $parte2, $sequencialAno] = explode('-', $numeroControlePNCP);
  [$sequencial, $ano] = explode('/', $sequencialAno);

  $pagina = 1;
  $ok = false;

  while (true) {
    $urlItens = "https://pncp.gov.br/api/pncp/v1/orgaos/$cnpj/compras/$ano/$sequencial/itens?pagina=$pagina&tamanhoPagina=50";
    $itens = baixarJson($urlItens);

    if ($itens === null) {
      logar("   ⚠️ Falha ao consultar itens de $numeroControlePNCP (página $pagina)");
      registrarFalha($pdo, $numeroControlePNCP, "Falha ao consultar itens (página $pagina)");
      return false;
    }

    if (!is_array($itens) || empty($itens)) {
      break; // acabou
    }

    foreach ($itens as $it) {
      $sql = "INSERT INTO cache_pncp_itens
                        (numeroControlePNCP, descricao, quantidade, valorUnitarioEstimado, valorUnitarioHomologado, sigiloso, json_original)
                    VALUES
                        (:num, :desc, :qtd, :vEst, :vHom, :sig, :json)";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        ':num'  => $numeroControlePNCP,
        ':desc' => $it['descricao'] ?? '',
        ':qtd'  => $it['quantidade'] ?? null,
        ':vEst' => $it['valorUnitarioEstimado'] ?? null,
        ':vHom' => $it['valorUnitarioHomologado'] ?? null,
        ':sig'  => $it['sigiloso'] ?? 0,
        ':json' => json_encode($it, JSON_UNESCAPED_UNICODE)
      ]);
    }

    logar("   📦 Itens salvos para $numeroControlePNCP (página $pagina) — " . count($itens) . " itens");
    $ok = true;

    if (count($itens) < 50) break;
    $pagina++;
  }

  return $ok;
}

/**
 * salvarItensDoProcesso_alternativo
 * - tenta o endpoint /consulta/v1/itens/{numeroControlePNCP}
 * - alguns números de controle funcionam melhor neste endpoint
 */
function salvarItensDoProcesso_alternativo(PDO $pdo, string $numeroControlePNCP): bool
{
  $url = "https://pncp.gov.br/api/consulta/v1/itens/{$numeroControlePNCP}";
  $r = consultarApi($url);

  if ($r === false) {
    logar("⚠️ Alternativo: cURL/timeout ao buscar itens de $numeroControlePNCP");
    registrarFalha($pdo, $numeroControlePNCP, "Falha ao consultar itens (alternativo cURL)");
    return false;
  }
  if ($r === null) {
    logar("⚠️ Alternativo: HTTP 400 para itens de $numeroControlePNCP");
    registrarFalha($pdo, $numeroControlePNCP, "Falha ao consultar itens (alternativo 400)");
    return false;
  }

  $dados = $r['data'] ?? $r;
  if (!is_array($dados) || count($dados) === 0) {
    logar("⚠️ Alternativo: nenhum item retornado para $numeroControlePNCP");
    registrarFalha($pdo, $numeroControlePNCP, "Falha ao consultar itens (alternativo vazio)");
    return false;
  }

  try {
    $pdo->prepare("DELETE FROM cache_pncp_itens WHERE numeroControlePNCP = ?")->execute([$numeroControlePNCP]);
  } catch (Throwable $e) {
    logar("⚠️ Alternativo: falha ao deletar itens antigos: " . $e->getMessage());
  }

  $ins = $pdo->prepare("INSERT INTO cache_pncp_itens
        (numeroControlePNCP, numeroItem, descricao, quantidade, valorUnitarioEstimado, valorUnitarioHomologado, json_original, dataAbertura)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");

  foreach ($dados as $it) {
    $numItem = $it['numeroItem'] ?? ($it['numero'] ?? null);
    $desc = $it['descricao'] ?? ($it['descricaoItem'] ?? '');
    $qtd = is_numeric($it['quantidade'] ?? null) ? $it['quantidade'] : null;
    $vEst = $it['valorUnitarioEstimado'] ?? null;
    $vHom = $it['valorUnitarioHomologado'] ?? null;
    $jsonIt = json_encode($it, JSON_UNESCAPED_UNICODE);

    try {
      $ins->execute([$numeroControlePNCP, $numItem, $desc, $qtd, $vEst, $vHom, $jsonIt]);
    } catch (Throwable $e) {
      logar("⚠️ Alternativo: erro inserindo item: " . $e->getMessage());
    }
  }

  logar("📦 Alternativo: itens salvos para $numeroControlePNCP (" . count($dados) . " itens)");
  return true;
}
