<?php
/**
 * Armazenamento usado pela sincronização com o iCloud.
 * O motor (IcloudSyncEngine) só conversa com esta interface: no app a implementação é PostgreSQL
 * (IcloudPgStore); nos testes, uma em memória.
 */
interface IcloudStore
{
    /** @return array{habilitado:bool, calendario_href:?string, home_url:?string, calendarios:array, ultima_sync:?string, ultimo_resultado:?string} */
    public function estado(): array;

    public function salvarEstado(array $parcial): void;

    /** Marcador de tempo do início da execução (para descobrir importados que sumiram). */
    public function inicioExecucao(): string;

    /** Eventos criados no app (ou adotados do calendário do app), com 'sujo' = mudou desde a última sincronização. */
    public function eventosApp(): array;

    /** $versao = 'versao' do evento lida antes do envio: se foi editado depois, continua marcado como alterado. */
    public function marcarEnviado(string $id, string $uid, string $href, ?string $etag, ?string $versao = null): void;

    public function limparLink(string $id): void;

    /** @return array<int, array{href:string, etag:?string}> */
    public function exclusoesPendentes(): array;

    public function removerExclusao(string $href): void;

    public function aplicarRemotoApp(string $id, array $campos, string $href, ?string $etag): void;

    public function adotarRemoto(array $campos, string $uid, string $href, ?string $etag): void;

    public function excluirLocal(string $id): void;

    public function upsertImportado(array $campos, string $uidChave, string $calHref, string $calNome): void;

    public function removerImportadosAntigos(string $calHref, string $marcador, string $janelaIni, string $janelaFim): int;

    public function removerImportadosForaDe(array $calHrefs): int;

    /**
     * Vencimentos financeiros (fatura, assinatura, empréstimo, despesa prevista) entre duas datas Y-m-d.
     * @return array<int, array{origem:string, id:string, titulo:string, data:string}>
     */
    public function vencimentos(string $inicio, string $fim): array;
}
