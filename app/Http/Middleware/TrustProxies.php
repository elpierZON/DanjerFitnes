<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * En AWS la EC2 solo recibe tráfico del Application Load Balancer
     * (security group sg-ec2), así que se confía en cualquier proxy para
     * que Laravel detecte HTTPS y genere enlaces https:// correctos.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '*';

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;

    /**
     * CloudFront atiende al visitante por HTTPS y llama al ALB por HTTP, y el
     * ALB reescribe X-Forwarded-Proto a "http". CloudFront envía el protocolo
     * real en CloudFront-Forwarded-Proto: lo usamos para que Laravel sepa que
     * la petición es segura (enlaces https://, cookies Secure, redirecciones).
     *
     * El ALB solo acepta tráfico de CloudFront (security group "alb"), así que
     * esta cabecera no puede llegar falsificada desde Internet.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $proto = strtolower((string) $request->headers->get('CloudFront-Forwarded-Proto'));

        if (in_array($proto, ['http', 'https'], true)) {
            $request->headers->set('X-Forwarded-Proto', $proto);
            $request->headers->set('X-Forwarded-Port', $proto === 'https' ? '443' : '80');
        }

        return parent::handle($request, $next);
    }
}
