<?php

namespace Symfony\Component\Notifier\Bridge\LinkedIn\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\LinkedIn\LinkedInTransport;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class LinkedInTransportTest extends TestCase
{
    public function testToString(): void
    {
        $this->assertSame(sprintf('linkedin://host.test'), (string) $this->getTransport());
    }

    public function testSupportsChatMessage(): void
    {
        $transport = $this->getTransport();

        $this->assertTrue($transport->supports(new ChatMessage('testChatMessage')));
        $this->assertFalse($transport->supports($this->createMock(MessageInterface::class)));
    }

    public function testSendNonChatMessageThrows(): void
    {
        $this->expectException(LogicException::class);

        $transport = $this->getTransport();

        $transport->send($this->createMock(MessageInterface::class));
    }

    public function testSendWithEmptyArrayResponseThrows(): void
    {
        $this->expectException(TransportException::class);

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(500);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('[]');

        $client = new MockHttpClient(static function () use ($response): ResponseInterface {
            return $response;
        });

        $transport = $this->getTransport($client);

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendWithErrorResponseThrows(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('testErrorCode');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(400);

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('testErrorCode');

        $client = new MockHttpClient(static function () use ($response): ResponseInterface {
            return $response;
        });

        $transport = $this->getTransport($client);

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendWithOptions(): void
    {
        $message = 'testMessage';

        $response = $this->createMock(ResponseInterface::class);

        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(201);

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['id' => '42']));

        $expectedBody = json_encode([
            'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                            'shareCommentary' => [
                                    'attributes' => [],
                                    'text' => 'testMessage',
                                ],
                            'shareMediaCategory' => 'NONE',
                        ],
                    ],
            'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
                ],
            'lifecycleState' => 'PUBLISHED',
            'author' => 'urn:li:person:MyLogin',
        ]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use (
            $response,
            $expectedBody
        ): ResponseInterface {
            $this->assertSame($expectedBody, $options['body']);

            return $response;
        });
        $transport = $this->getTransport($client);

        $transport->send(new ChatMessage($message));
    }

    public function testSendWithNotification(): void
    {
        $message = 'testMessage';

        $response = $this->createMock(ResponseInterface::class);

        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(201);

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['id' => '42']));

        $notification = new Notification($message);
        $chatMessage = ChatMessage::fromNotification($notification);

        $expectedBody = json_encode([
            'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                            'shareCommentary' => [
                                    'attributes' => [],
                                    'text' => 'testMessage',
                                ],
                            'shareMediaCategory' => 'NONE',
                        ],
                    ],
            'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
                ],
            'lifecycleState' => 'PUBLISHED',
            'author' => 'urn:li:person:MyLogin',
        ]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use (
            $response,
            $expectedBody
        ): ResponseInterface {
            $this->assertSame($expectedBody, $options['body']);

            return $response;
        });

        $transport = $this->getTransport($client);

        $transport->send($chatMessage);
    }

    public function testSendWithInvalidOptions(): void
    {
        $this->expectException(LogicException::class);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []): ResponseInterface {
            return $this->createMock(ResponseInterface::class);
        });

        $transport = $this->getTransport($client);

        $transport->send(new ChatMessage('testMessage', $this->createMock(MessageOptionsInterface::class)));
    }

    public function getTransport($client = null)
    {
        return (new LinkedInTransport(
            'MyToken',
            'MyLogin',
            $client ?? $this->createMock(HttpClientInterface::class)
        ))->setHost('host.test');
    }
}
