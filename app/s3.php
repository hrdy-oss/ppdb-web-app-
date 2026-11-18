<?php
declare(strict_types=1);

/**
 * Klien S3 minimal (AWS Signature V4) tanpa SDK/Composer.
 * Kredensial: .env (AWS_ACCESS_KEY_ID, dst) atau IAM Role instance (IMDSv2) jika .env kosong.
 */
class S3
{
    public function __construct(private string $bucket, private string $region) {}

    private function credentials(): array
    {
        $k = env('AWS_ACCESS_KEY_ID');
        $s = env('AWS_SECRET_ACCESS_KEY');
        if ($k && $s) return [$k, $s, env('AWS_SESSION_TOKEN')];

        static $cache = null;
        if ($cache) return $cache;
        $base = 'http://169.254.169.254/latest';
        $token = $this->http('PUT', "$base/api/token", ['X-aws-ec2-metadata-token-ttl-seconds: 300'])[1];
        $h = ["X-aws-ec2-metadata-token: $token"];
        $role = trim($this->http('GET', "$base/meta-data/iam/security-credentials/", $h)[1]);
        $j = json_decode($this->http('GET', "$base/meta-data/iam/security-credentials/$role", $h)[1], true);
        if (!isset($j['AccessKeyId'])) throw new RuntimeException('Kredensial S3 tidak tersedia');
        return $cache = [$j['AccessKeyId'], $j['SecretAccessKey'], $j['Token'] ?? null];
    }

    private function http(string $method, string $url, array $headers = [], string $body = ''): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 30,
        ]);
        if ($body !== '') curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $res = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$code, $res === false ? '' : (string)$res];
    }

    private function request(string $method, string $key, string $body = '', string $contentType = ''): array
    {
        [$ak, $sk, $tok] = $this->credentials();
        $host = "{$this->bucket}.s3.{$this->region}.amazonaws.com";
        $uri = '/' . implode('/', array_map('rawurlencode', explode('/', $key)));
        $amzDate = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $payloadHash = hash('sha256', $body);

        $h = ['host' => $host, 'x-amz-content-sha256' => $payloadHash, 'x-amz-date' => $amzDate];
        if ($tok) $h['x-amz-security-token'] = $tok;
        if ($contentType !== '') $h['content-type'] = $contentType;
        ksort($h);
        $canonHeaders = '';
        foreach ($h as $n => $v) $canonHeaders .= "$n:$v\n";
        $signed = implode(';', array_keys($h));

        $canonReq = "$method\n$uri\n\n$canonHeaders\n$signed\n$payloadHash";
        $scope = "$date/{$this->region}/s3/aws4_request";
        $sts = "AWS4-HMAC-SHA256\n$amzDate\n$scope\n" . hash('sha256', $canonReq);
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $sk, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $sig = hash_hmac('sha256', $sts, $kSigning);

        $headers = ["Authorization: AWS4-HMAC-SHA256 Credential=$ak/$scope, SignedHeaders=$signed, Signature=$sig"];
        foreach ($h as $n => $v) if ($n !== 'host') $headers[] = "$n: $v";
        return $this->http($method, "https://$host$uri", $headers, $body);
    }

    public function put(string $key, string $body, string $contentType): bool
    {
        return $this->request('PUT', $key, $body, $contentType)[0] === 200;
    }

    /** @return array{0:int,1:string} [http_code, body] */
    public function get(string $key): array
    {
        return $this->request('GET', $key);
    }

    public function delete(string $key): bool
    {
        return in_array($this->request('DELETE', $key)[0], [200, 204], true);
    }
}

function s3(): S3
{
    static $s = null;
    return $s ??= new S3((string)env('S3_BUCKET', ''), (string)env('S3_REGION', 'ap-southeast-1'));
}
