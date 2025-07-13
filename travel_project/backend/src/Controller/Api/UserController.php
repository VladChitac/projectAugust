<?php
// src/Controller/Api/UserController.php

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\PasswordResetRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

#[Route('/api/users', name: 'api_users_')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly MailerInterface $mailer,
    ) {}

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->json([
            'id'        => $user->getId(),
            'username'  => $user->getUsername(),
            'email'     => $user->getEmail(),
            'roles'     => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i'),
        ]);
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $req): JsonResponse
    {
        // Decode and validate JSON
        $data = json_decode($req->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON data'], 400);
        }

        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            return $this->json(['error' => 'All fields are required'], 400);
        }

        // Sanitize input
        $username = trim($data['username']);
        $email = trim(strtolower($data['email']));
        $password = $data['password'];

        // Validate username
        if (strlen($username) < 3 || strlen($username) > 50) {
            return $this->json(['error' => 'Username must be between 3 and 50 characters'], 400);
        }
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
            return $this->json(['error' => 'Username can only contain letters, numbers, dots, hyphens and underscores'], 400);
        }

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Please enter a valid email address'], 400);
        }
        if (strlen($email) > 180) {
            return $this->json(['error' => 'Email cannot be longer than 180 characters'], 400);
        }

        // Validate password strength
        if (strlen($password) < 8) {
            return $this->json(['error' => 'Password must be at least 8 characters long'], 400);
        }
        if (!preg_match('/[a-zA-Z]/', $password)) {
            return $this->json(['error' => 'Password must contain at least one letter'], 400);
        }
        if (!preg_match('/[0-9]/', $password)) {
            return $this->json(['error' => 'Password must contain at least one number'], 400);
        }

        // Check if email already exists
        if ($this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'Email is already registered'], 409);
        }

        // Check if username already exists
        if ($this->em->getRepository(User::class)->findOneBy(['username' => $username])) {
            return $this->json(['error' => 'Username is already taken'], 409);
        }

        try {
            $user = (new User())
                ->setUsername($username)
                ->setEmail($email)
                ->setRole('user');
            
            $user->setPassword(
                $this->hasher->hashPassword($user, $password)
            );

            $this->em->persist($user);
            $this->em->flush();

            return $this->json(['message' => 'User registered successfully'], 201);
        } catch (\Exception $e) {
            // Log the actual error for debugging but don't expose it to the user
            error_log('Registration error: ' . $e->getMessage());
            return $this->json(['error' => 'Registration failed'], 500);
        }
    }

    #[Route('/forgot-password', name: 'forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $req): JsonResponse
    {
        // Decode and validate JSON
        $data = json_decode($req->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON data'], 400);
        }

        if (empty($data['email'])) {
            return $this->json(['error' => 'Email is required'], 400);
        }

        // Sanitize and validate email
        $email = trim(strtolower($data['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Please enter a valid email address'], 400);
        }

        /** @var User|null $user */
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        
        // Always return success message for security (don't reveal if email exists)
        if (!$user) {
            return $this->json(['message' => 'Лист із лінком надіслано']);
        }

        // Створюємо запит на відновлення пароля
        $pr = new PasswordResetRequest($user);
        $this->em->persist($pr);
        $this->em->flush();

        // Відправка листа
        $resetLink = sprintf(
            '%s/reset-password/%s',
            $_ENV['APP_FRONTEND_URL'] ?? 'http://localhost:5177',
            $pr->getToken()
        );

        $email = (new TemplatedEmail())
            ->from(new Address('katrinper6@gmail.com', 'Travel App Support'))
            ->to($user->getEmail())
            ->subject('Відновлення пароля')
            ->htmlTemplate('emails/reset_password.html.twig')
            ->context([
                'username'  => $user->getUsername(),
                'resetLink' => $resetLink,
            ]);

        $this->mailer->send($email);

        return $this->json(['message' => 'Лист із лінком надіслано']);
    }

    #[Route('/create-admin', name: 'create_admin', methods: ['POST'])]
    public function createAdmin(Request $req): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Decode and validate JSON
        $data = json_decode($req->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON data'], 400);
        }

        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            return $this->json(['error' => 'All fields are required'], 400);
        }

        // Sanitize input
        $username = trim($data['username']);
        $email = trim(strtolower($data['email']));
        $password = $data['password'];

        // Validate username
        if (strlen($username) < 3 || strlen($username) > 50) {
            return $this->json(['error' => 'Username must be between 3 and 50 characters'], 400);
        }
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
            return $this->json(['error' => 'Username can only contain letters, numbers, dots, hyphens and underscores'], 400);
        }

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Please enter a valid email address'], 400);
        }

        // Validate password strength
        if (strlen($password) < 8) {
            return $this->json(['error' => 'Password must be at least 8 characters long'], 400);
        }
        if (!preg_match('/[a-zA-Z]/', $password)) {
            return $this->json(['error' => 'Password must contain at least one letter'], 400);
        }
        if (!preg_match('/[0-9]/', $password)) {
            return $this->json(['error' => 'Password must contain at least one number'], 400);
        }

        // Check if email already exists
        if ($this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'Email is already registered'], 409);
        }

        // Check if username already exists
        if ($this->em->getRepository(User::class)->findOneBy(['username' => $username])) {
            return $this->json(['error' => 'Username is already taken'], 409);
        }

        try {
            $user = (new User())
                ->setUsername($username)
                ->setEmail($email)
                ->setRole('admin');
            
            $user->setPassword(
                $this->hasher->hashPassword($user, $password)
            );

            $this->em->persist($user);
            $this->em->flush();

            return $this->json(['message' => 'Admin created successfully'], 201);
        } catch (\Exception $e) {
            error_log('Admin creation error: ' . $e->getMessage());
            return $this->json(['error' => 'Admin creation failed'], 500);
        }
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $users = $this->em->getRepository(User::class)->findAll();
        $data = array_map(fn(User $u) => [
            'id'        => $u->getId(),
            'username'  => $u->getUsername(),
            'email'     => $u->getEmail(),
            'role'     => $u->getRole(),
            'createdAt' => $u->getCreatedAt()->format('Y-m-d'),
        ], $users);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $req): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $u = $this->em->find(User::class, $id) ?? null;
        if (!$u) return $this->json(['error'=>'Not found'], 404);

        $d = json_decode($req->getContent(), true) ?? [];
        if (isset($d['username'])) $u->setUsername($d['username']);
        if (isset($d['email']))    $u->setEmail($d['email']);
        if (isset($d['role']) && \in_array($d['role'], ['user','admin'], true)) {
            $u->setRole($d['role']);
        }
        $this->em->flush();

        return $this->json(['saved'=>true]);
    }


    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        /** @var User|null $user */
        $user = $this->em->find(User::class, $id);
        if (!$user) {
            return $this->json(['error' => 'Not found'], 404);
        }
        $this->em->remove($user);
        $this->em->flush();

        return $this->json(['message' => 'User deleted']);
    }

    #[Route('/{id}/reset-password', name: 'reset_password', methods: ['POST'])]
    public function resetPassword(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        /** @var User|null $user */
        $user = $this->em->find(User::class, $id);
        if (!$user) {
            return $this->json(['error' => 'Not found'], 404);
        }

        // Створюємо запит на відновлення пароля
        $pr = new PasswordResetRequest($user);
        $this->em->persist($pr);
        $this->em->flush();

        // Відправка листа
        $resetLink = sprintf(
            '%s/reset-password/%s',
            $_ENV['APP_FRONTEND_URL'] ?? 'http://localhost:5177',
            $pr->getToken()
        );

        $email = (new TemplatedEmail())
            ->from(new Address('katrinper6@gmail.com', 'Travel App Support'))
            ->to($user->getEmail())
            ->subject('Відновлення пароля')
            ->htmlTemplate('emails/reset_password.html.twig')
            ->context([
                'username'  => $user->getUsername(),
                'resetLink' => $resetLink,
            ]);

        $this->mailer->send($email);

        return $this->json(['message' => 'Лист із лінком надіслано']);
    }
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $req): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Decode and validate JSON
        $data = json_decode($req->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON data'], 400);
        }

        foreach (['username', 'email', 'password'] as $field) {
            if (empty($data[$field])) {
                return $this->json(["error" => "Missing $field"], 400);
            }
        }

        // Sanitize input
        $username = trim($data['username']);
        $email = trim(strtolower($data['email']));
        $password = $data['password'];

        // Validate username
        if (strlen($username) < 3 || strlen($username) > 50) {
            return $this->json(['error' => 'Username must be between 3 and 50 characters'], 400);
        }
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
            return $this->json(['error' => 'Username can only contain letters, numbers, dots, hyphens and underscores'], 400);
        }

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Please enter a valid email address'], 400);
        }

        // Validate password strength
        if (strlen($password) < 8) {
            return $this->json(['error' => 'Password must be at least 8 characters long'], 400);
        }
        if (!preg_match('/[a-zA-Z]/', $password)) {
            return $this->json(['error' => 'Password must contain at least one letter'], 400);
        }
        if (!preg_match('/[0-9]/', $password)) {
            return $this->json(['error' => 'Password must contain at least one number'], 400);
        }

        // Check if email already exists
        if ($this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'Email is already registered'], 409);
        }

        // Check if username already exists
        if ($this->em->getRepository(User::class)->findOneBy(['username' => $username])) {
            return $this->json(['error' => 'Username is already taken'], 409);
        }

        $role = ($data['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

        try {
            $user = (new User())
                ->setUsername($username)
                ->setEmail($email)
                ->setRole($role);
            
            $user->setPassword($this->hasher->hashPassword($user, $password));

            $this->em->persist($user);
            $this->em->flush();

            return $this->json(['id' => $user->getId(), 'role' => $user->getRole()], 201);
        } catch (\Exception $e) {
            error_log('User creation error: ' . $e->getMessage());
            return $this->json(['error' => 'User creation failed'], 500);
        }
    }

    #[Route('/reset-password-token/{token}', name: 'reset_password_with_token', methods: ['POST'])]
    public function resetPasswordWithToken(string $token, Request $req): JsonResponse
    {
        // Decode and validate JSON
        $data = json_decode($req->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON data'], 400);
        }

        if (empty($data['password'])) {
            return $this->json(['error' => 'Password is required'], 400);
        }

        $password = $data['password'];

        // Validate password strength
        if (strlen($password) < 8) {
            return $this->json(['error' => 'Password must be at least 8 characters long'], 400);
        }
        if (!preg_match('/[a-zA-Z]/', $password)) {
            return $this->json(['error' => 'Password must contain at least one letter'], 400);
        }
        if (!preg_match('/[0-9]/', $password)) {
            return $this->json(['error' => 'Password must contain at least one number'], 400);
        }

        // Find the password reset request using repository method
        $passwordResetRequest = $this->em->getRepository(PasswordResetRequest::class)
            ->findValidToken($token);

        if (!$passwordResetRequest) {
            return $this->json(['error' => 'Invalid or expired token'], 404);
        }

        try {
            // Reset the password
            $user = $passwordResetRequest->getUser();
            $hashedPassword = $this->hasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);

            // Remove the used token
            $this->em->remove($passwordResetRequest);
            $this->em->flush();

            return $this->json(['message' => 'Password has been reset successfully']);
        } catch (\Exception $e) {
            error_log('Password reset error: ' . $e->getMessage());
            return $this->json(['error' => 'Password reset failed'], 500);
        }
    }

}
