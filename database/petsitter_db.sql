-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mar. 02 juin 2026 à 17:00
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `petsitter_db`
--

-- --------------------------------------------------------

--
-- Structure de la table `animal`
--

CREATE TABLE `animal` (
  `animalID` int(11) NOT NULL,
  `Name` varchar(100) NOT NULL,
  `CreationDate` datetime NOT NULL DEFAULT current_timestamp(),
  `User_userID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `application`
--

CREATE TABLE `application` (
  `appID` int(11) NOT NULL,
  `CreationDate` datetime NOT NULL,
  `Status` varchar(45) NOT NULL,
  `User_userID` int(11) NOT NULL,
  `Post_postID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `cgu_versions`
--

CREATE TABLE `cgu_versions` (
  `id` int(11) NOT NULL,
  `version_number` varchar(50) NOT NULL COMMENT 'e.g. 1.0, 1.1 — groups all sections of one CGU version',
  `section_title` varchar(255) NOT NULL COMMENT 'Section heading displayed to users',
  `content` text NOT NULL COMMENT 'Full text of this section',
  `effective_from` date NOT NULL COMMENT 'Date this version takes effect',
  `is_active` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = currently shown to users; only one version active at a time',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `cgu_versions`
--

INSERT INTO `cgu_versions` (`id`, `version_number`, `section_title`, `content`, `effective_from`, `is_active`, `created_at`) VALUES
(1, '1.0', '1. Acceptance of Terms', 'By accessing and using Petsitter\'s Market (\"the Service\"), you accept and agree to be bound by the terms and provision of this agreement. If you do not agree to abide by the above, please do not use this service.\r\n\r\n- These terms apply to all users of the platform\r\n- You must be at least 18 years old to use our services\r\n- You are responsible for maintaining account security', '2024-01-01', 1, '2026-05-28 22:16:09'),
(2, '1.0', '2. Service Description', 'Petsitter\'s Market is a platform that connects pet owners with qualified pet care providers. Our services include:\r\n\r\n- Pet sitting and boarding services\r\n- Dog walking and exercise services\r\n- Pet grooming and care services\r\n- Emergency pet care coordination\r\n- Secure payment processing\r\n- Rating and review system', '2024-01-01', 1, '2026-05-28 22:16:09'),
(3, '1.0', '3. User Responsibilities', '3.1 Pet Owners:\r\n- Provide accurate information about your pets\r\n- Ensure pets are up-to-date on vaccinations\r\n- Disclose any behavioral issues or special needs\r\n- Pay for services as agreed upon\r\n\r\n3.2 Pet Sitters:\r\n- Provide services as described in your profile\r\n- Maintain professional conduct at all times\r\n- Report any incidents or emergencies immediately\r\n- Respect client property and privacy', '2024-01-01', 1, '2026-05-28 22:16:09'),
(4, '1.0', '4. Payment and Fees', 'All payments are processed securely through our platform. The following terms apply:\r\n\r\n1. Service fees are due upon booking confirmation\r\n2. Cancellation policies vary by service provider\r\n3. Platform fees are automatically deducted from payments\r\n4. Refunds are processed according to our refund policy\r\n5. Dispute resolution may result in payment holds', '2024-01-01', 1, '2026-05-28 22:16:09'),
(5, '1.0', '5. Liability and Insurance', 'While we strive to connect you with qualified pet care providers, please note:\r\n\r\n- Petsitter\'s Market acts as a platform facilitator only\r\n- All service agreements are between users directly\r\n- We recommend obtaining appropriate insurance coverage\r\n- Emergency procedures must be followed as outlined\r\n- Report incidents within 24 hours of occurrence', '2024-01-01', 1, '2026-05-28 22:16:09'),
(6, '1.0', '6. Privacy and Data Protection', 'Your privacy is important to us. We collect and use information as described in our Privacy Policy:\r\n\r\n- Personal information is protected and encrypted\r\n- Data is used only for service provision and improvement\r\n- Third-party sharing is limited and disclosed\r\n- Users can request data deletion at any time', '2024-01-01', 1, '2026-05-28 22:16:09'),
(7, '1.0', '7. Termination', 'Either party may terminate this agreement under the following conditions:\r\n\r\n1. Violation of terms and conditions\r\n2. Fraudulent or misleading information\r\n3. Failure to pay for services\r\n4. Inappropriate conduct or behavior\r\n5. Request for account deletion', '2024-01-01', 1, '2026-05-28 22:16:09'),
(8, '1.0', '8. Contact Information', 'If you have any questions about these Terms of Use, please contact us:\r\n\r\nEmail: legal@petsittersmarket.com\r\nPhone: 1-800-PET-CARE\r\nAddress: 123 Pet Care Avenue, Animal City, AC 12345', '2024-01-01', 1, '2026-05-28 22:16:09');

-- --------------------------------------------------------

--
-- Structure de la table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('non_traite','en_cours','archive') NOT NULL DEFAULT 'non_traite',
  `reply_message` text DEFAULT NULL,
  `replied_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `faq`
--

CREATE TABLE `faq` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `question` varchar(255) NOT NULL,
  `answer` text NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = hidden, 1 = visible',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `faq`
--

INSERT INTO `faq` (`id`, `category_id`, `question`, `answer`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'How do you verify pet sitters?', 'All pet sitters go through a background check, identity verification, and profile review before being listed on our platform. We also collect references and verify past pet care experience.', 1, 1, '2026-05-26 08:03:35', '2026-05-26 08:03:35'),
(2, 1, 'Is my pet insured during sitting sessions?', 'We strongly recommend pet owners to have their own pet insurance. While sitters are encouraged to carry liability coverage, the platform itself acts as a marketplace and does not provide direct insurance for individual sessions.', 2, 1, '2026-05-26 08:03:35', '2026-05-26 08:03:35'),
(3, 1, 'What happens in case of an emergency?', 'Sitters are trained to contact the pet owner immediately and, if necessary, take the pet to the nearest veterinary clinic. You can provide emergency vet details in your pet profile so the sitter always has them on hand.', 3, 1, '2026-05-26 08:03:35', '2026-05-26 08:03:35'),
(4, 1, 'Can I meet the sitter before booking?', 'Absolutely. We encourage a meet-and-greet before any first booking. You can arrange this directly with the sitter through our messaging system — there is no additional charge for an introductory meeting.', 4, 1, '2026-05-26 08:03:35', '2026-05-26 08:03:35'),
(5, 2, 'What payment methods do you accept?', 'We accept all major credit and debit cards (Visa, Mastercard, American Express), as well as PayPal. Bank transfers are available for recurring bookings on request.', 1, 1, '2026-05-26 08:03:35', '2026-05-26 08:03:35'),
(6, 2, 'When am I charged for a booking?', 'Payment is captured at the time of booking confirmation. For stays longer than 7 days, we may split the charge into two installments — one at booking and one 24 hours before the service begins.', 2, 1, '2026-05-26 08:03:35', '2026-05-26 08:03:35'),
(7, 2, 'What is your cancellation and refund policy?', 'Cancellations made more than 48 hours in advance receive a full refund. Cancellations within 48 hours may be subject to a partial fee depending on the sitter individual policy, which is displayed on their profile before booking.', 3, 1, '2026-05-26 08:03:35', '2026-05-26 08:03:35'),
(8, 2, 'Are there any additional fees?', 'A small platform service fee (typically 5-10%) is added at checkout. This covers secure payment processing, customer support, and platform maintenance. There are no hidden charges beyond what is shown at confirmation.', 4, 1, '2026-05-26 08:03:35', '2026-05-26 08:03:35'),
(9, 3, 'What types of pets do you support?', 'Most sitters care for dogs and cats, but many also support rabbits, birds, reptiles, fish, and small animals like guinea pigs. Each sitter lists the species they are comfortable with on their profile.', 1, 1, '2026-05-26 08:03:35', '2026-05-26 08:03:35'),
(10, 3, 'How do I become a pet sitter on your platform?', 'Create a free account, select Pet Sitter as your role, complete your profile with your experience and available services, and submit your verification documents. Our team reviews applications within 3 business days.', 2, 1, '2026-05-26 08:03:35', '2026-05-26 08:03:35'),
(11, 3, 'Do sitters provide updates during the sitting?', 'Yes — sitters are encouraged to send daily photo or video updates through our messaging system. You can specify the frequency and format you prefer when setting up your booking.', 3, 1, '2026-05-26 08:03:35', '2026-05-26 08:03:35'),
(12, 3, 'What information should I provide about my pet?', 'In your pet profile you can include breed, age, diet, medical conditions, medications, behavioural notes, emergency vet contact, and any special instructions. The more detail you provide, the better your sitter can care for your pet.', 4, 1, '2026-05-26 08:03:35', '2026-05-26 08:03:35'),
(13, 4, 'How far in advance should I book?', 'We recommend booking at least 1 week in advance for regular stays, and 2-4 weeks for holidays or peak periods. Last-minute bookings are possible but subject to sitter availability.', 1, 1, '2026-05-26 08:03:35', '2026-05-26 08:03:35'),
(14, 4, 'Can I book recurring services?', 'Yes. You can set up recurring bookings on a weekly or monthly basis directly with your preferred sitter. Recurring clients often receive a small discount at the sitter discretion.', 2, 1, '2026-05-26 08:03:35', '2026-05-26 08:03:35'),
(15, 4, 'What if my plans change and I need to extend my booking?', 'Contact your sitter via the messaging system as soon as possible. If they are available, extensions can be added to the booking directly from your dashboard. Any additional charge is calculated at the same daily rate.', 3, 1, '2026-05-26 08:03:35', '2026-05-26 08:03:35'),
(16, 4, 'How does the review system work?', 'After each completed booking, you are invited to leave a star rating and written review for your sitter. Reviews are visible on their public profile. Each owner can leave one review per sitter, and reviews cannot be edited after 48 hours.', 4, 1, '2026-05-26 08:03:35', '2026-05-26 08:03:35');

-- --------------------------------------------------------

--
-- Structure de la table `faq_category`
--

CREATE TABLE `faq_category` (
  `id` int(11) NOT NULL,
  `slug` varchar(50) NOT NULL COMMENT 'used for filter tabs, e.g. safety',
  `label` varchar(100) NOT NULL COMMENT 'display name, e.g. Safety & Security',
  `icon` varchar(50) DEFAULT NULL COMMENT 'font-awesome class, e.g. fa-shield-alt',
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `faq_category`
--

INSERT INTO `faq_category` (`id`, `slug`, `label`, `icon`, `sort_order`) VALUES
(1, 'safety', 'Safety & Security', 'fa-shield-alt', 1),
(2, 'payments', 'Payments & Pricing', 'fa-credit-card', 2),
(3, 'general', 'General Information', 'fa-info-circle', 3),
(4, 'booking', 'Booking & Scheduling', 'fa-calendar-alt', 4);

-- --------------------------------------------------------

--
-- Structure de la table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 1,
  `last_attempt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `post`
--

CREATE TABLE `post` (
  `postID` int(11) NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Description` text DEFAULT NULL,
  `Price` decimal(10,2) NOT NULL,
  `CreationDate` datetime NOT NULL,
  `Visibility` tinyint(4) NOT NULL,
  `Applicability` varchar(45) DEFAULT NULL,
  `User_userID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `post_has_animal`
--

CREATE TABLE `post_has_animal` (
  `Post_postID` int(11) NOT NULL,
  `Animal_animalID` int(11) NOT NULL,
  `timepost` datetime DEFAULT NULL,
  `Post_has_Animalcol` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `rating`
--

CREATE TABLE `rating` (
  `ratingID` int(11) NOT NULL,
  `Score` int(11) NOT NULL,
  `Description` text DEFAULT NULL,
  `CreationDate` datetime NOT NULL,
  `Rated_userID` int(11) NOT NULL,
  `Rater_userID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `selector` varchar(32) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `remember_tokens`
--

INSERT INTO `remember_tokens` (`id`, `user_id`, `selector`, `token_hash`, `expires_at`, `created_at`) VALUES
(2, 1, '2cab6e9eb156bfebacbf6a465da9d0db', 'c7682674c088396c7ac22e7ce9dcff5b84f51ae0e3edf969dc6886e2db1610de', '2026-06-18 15:50:22', '2026-05-19 13:50:22'),
(3, 1, 'f006d29278301c2c161cc0aac204ef13', '5eb847344b50bd21d1b23a632fe25901ebae354ab7c48c7841ebe3586c30df13', '2026-06-18 15:53:25', '2026-05-19 13:53:25');

-- --------------------------------------------------------

--
-- Structure de la table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `rater_user_id` int(11) NOT NULL COMMENT 'User who wrote the review',
  `rated_user_id` int(11) NOT NULL COMMENT 'User being reviewed',
  `rating` int(11) NOT NULL,
  `review_text` varchar(250) DEFAULT NULL,
  `is_disabled` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `reviews`
--

INSERT INTO `reviews` (`id`, `rater_user_id`, `rated_user_id`, `rating`, `review_text`, `is_disabled`, `created_at`, `updated_at`) VALUES
(1, 7, 2, 5, 'Sarah was incredible with Max! Daily photos and videos kept us at ease the whole trip. Max was so happy when we got home. Will 100% book again.', 0, '2024-01-20 09:00:00', '2026-05-12 20:07:49'),
(2, 7, 3, 4, 'Léo was great — very knowledgeable about dog behaviour. Max was a little hesitant at first but warmed up quickly thanks to Léo\'s patience.', 0, '2024-03-05 10:00:00', '2026-05-12 20:07:49'),
(3, 7, 5, 5, 'Jake took Max on the longest walks — came home exhausted and happy every day. Exactly what an energetic retriever needs!', 0, '2024-06-12 07:30:00', '2026-05-12 20:07:49'),
(4, 8, 2, 5, 'Sarah looked after Biscuit and Cream for 10 days and sent the cutest update photos. The boys were relaxed and well fed. Highly recommend!', 0, '2024-02-14 13:00:00', '2026-05-12 20:07:49'),
(5, 8, 4, 5, 'Maya\'s vet background gave me total peace of mind. She noticed Cream was a little off and knew exactly what to check. Above and beyond!', 0, '2024-04-22 06:00:00', '2026-05-12 20:07:49'),
(6, 8, 6, 4, 'Nina was comfortable with cats right away, which is not always the case. Biscuit even sat on her lap by day two!', 0, '2024-07-03 15:00:00', '2026-05-12 20:07:49'),
(7, 9, 2, 5, 'Sarah is wonderful with anxious dogs. Ziggy went from hiding under the sofa to following her around the flat. Truly remarkable.', 0, '2024-01-30 08:00:00', '2026-05-12 20:07:49'),
(8, 9, 3, 5, 'Léo specialises in reactive dogs and it showed. Ziggy made real progress during his stay. We are booking monthly now.', 0, '2024-05-10 08:30:00', '2026-05-12 20:07:49'),
(9, 9, 4, 4, 'Maya was great — very gentle with Ziggy. She asked all the right questions beforehand and respected his boundaries perfectly.', 0, '2024-08-01 10:00:00', '2026-05-12 20:07:49'),
(10, 10, 6, 5, 'Nina is the only sitter I trust with Kiwi. She knows exactly how to handle an African Grey — the right diet, stimulation, everything. Outstanding.', 0, '2024-03-18 14:00:00', '2026-05-12 20:07:49'),
(11, 10, 2, 3, 'Sarah was kind and attentive but admitted she had limited experience with parrots. She was honest about it, which I respect, but Kiwi was a bit stressed.', 0, '2024-06-25 09:00:00', '2026-05-12 20:07:49'),
(12, 11, 5, 5, 'Jake handled all three dachshunds without breaking a sweat. They adore him. He kept them active and disciplined — no small feat with Pretzel!', 0, '2024-02-28 09:00:00', '2026-05-12 20:07:49'),
(13, 11, 2, 5, 'Sarah managed the three chaos goblins like a pro. Each one got individual attention and they were all calm and happy throughout.', 0, '2024-05-20 07:00:00', '2026-05-12 20:07:49'),
(14, 11, 3, 4, 'Léo was patient and structured with the boys. They responded really well to his training approach. Wurst even learned sit properly!', 0, '2024-09-11 12:00:00', '2026-05-19 14:01:56');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `bio` text DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `is_admin` tinyint(1) DEFAULT 0,
  `is_sitter` tinyint(1) DEFAULT 0,
  `is_owner` tinyint(1) DEFAULT 1,
  `user_type` enum('pet-owner','pet-sitter','admin') DEFAULT 'pet-owner',
  `is_banned` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = active, 1 = banned',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `public_id` char(36) NOT NULL DEFAULT uuid()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `username`, `first_name`, `last_name`, `email`, `phone`, `password`, `bio`, `avatar_url`, `is_admin`, `is_sitter`, `is_owner`, `user_type`, `is_banned`, `created_at`, `public_id`) VALUES
(1, 'admin', 'Admin', 'Admin', 'admin.g7b@gmail.com', '', '$2y$12$1wt30tZ1jGpd.ACHuukmleb7RVHO1rFbLeOeq0znXFTLZDda6moga', '', NULL, 1, 0, 0, 'admin', 0, '2026-05-12 17:42:25', 'cce74343-5e01-11f1-9246-581122c548ef'),
(2, 'sarah_pawsome', 'Sarah', 'Mitchell', 'sarah.mitchell@example.com', '555-201-1001', '$2y$12$bK0nvkH1JJQFVyyYBWppZOeoWwnM.bhruivYO6BSAZVD9NYM.NHvi', 'Passionate animal lover with 6 years of hands-on experience caring for dogs, cats, birds, and small animals. I treat every pet like my own and send daily photo updates to owners.', NULL, 0, 1, 0, 'pet-sitter', 0, '2022-03-15 08:00:00', 'cce7446b-5e01-11f1-9246-581122c548ef'),
(3, 'leo_petcare', 'Léo', 'Dubois', 'leo.dubois@example.com', '555-201-1002', '$2y$12$4R5IblppwMCX43UCrphKtuHiN6o/y/8qd0U2Ro76SQvbhGZ2/7NWi', 'Certified animal behaviourist and full-time pet sitter based in the city. Specialist in anxious and reactive dogs. Fluent in French and English.', NULL, 0, 1, 0, 'pet-sitter', 0, '2021-07-22 08:30:00', 'cce74533-5e01-11f1-9246-581122c548ef'),
(4, 'maya_tails', 'Maya', 'Johnson', 'maya.johnson@example.com', '555-201-1003', '$2y$12$2ADnpaOsiDESpC9o.vSge.zHQ7nNlJ/m7Dgq3AAF1Id3HcerLqSuO', 'Veterinary nurse by trade, pet sitter by passion! I can handle medication administration, post-op care, and special diets. Your senior pets are very welcome.', NULL, 0, 1, 0, 'pet-sitter', 0, '2023-01-10 07:00:00', 'cce745b5-5e01-11f1-9246-581122c548ef'),
(5, 'jake_walkies', 'Jake', 'Thompson', 'jake.thompson@example.com', '555-201-1004', '$2y$12$9eED0Cyjy06kXpCntSjt0Omgm86FmEgz3a8r2KQ3AeKtzKVXHI4Vq', 'Dog trainer and avid hiker — I make sure your pup gets plenty of exercise and stimulation every single day. Large breeds and high-energy dogs are my speciality.', NULL, 0, 1, 0, 'pet-sitter', 0, '2022-09-05 09:15:00', 'cce746fb-5e01-11f1-9246-581122c548ef'),
(6, 'nina_feathers', 'Nina', 'Rossi', 'nina.rossi@example.com', '555-201-1005', '$2y$12$W3sQWShg2fTeu09ThrTYHuOwHEIp67T/2DaG2eVKJJ2ts6KYs/Xoy', 'Exotic pet specialist — birds, reptiles, rabbits, guinea pigs, and fish are all in my wheelhouse. I have cared for over 40 species in the past 5 years.', NULL, 0, 1, 0, 'pet-sitter', 0, '2020-11-30 13:00:00', 'cce74780-5e01-11f1-9246-581122c548ef'),
(7, 'mike_chen', 'Mike', 'Chen', 'mike.chen@example.com', '555-301-2001', '$2y$12$/Cg5YHgMV5P7SBOPKPR92e6MsmE/IzQhzS9oB7hPDz5cRUNn5FRVe', 'Owner of Max, a 3-year-old Golden Retriever who loves fetch and belly rubs.', NULL, 0, 0, 1, 'pet-owner', 0, '2023-02-14 08:00:00', 'cce747ff-5e01-11f1-9246-581122c548ef'),
(8, 'emma_wilson', 'Emma', 'Wilson', 'emma.wilson@example.com', '555-301-2002', '$2y$12$WBnINoz0F7t1.hJ8Vqn3Ce6GQHFWVVYwGrIPV5TjR80lFe4qAQ7ly', 'Cat mum to Biscuit and Cream, two tabby brothers who rule the flat.', NULL, 0, 0, 1, 'pet-owner', 0, '2022-06-01 08:00:00', 'cce74878-5e01-11f1-9246-581122c548ef'),
(9, 'david_park', 'David', 'Park', 'david.park@example.com', '555-301-2003', '$2y$12$5btj2x2uN/pqJmObubQR2.HNIw7Y6ticA37VIKAiYoMHbiA7V18wK', 'Rescue dog advocate. My pup Ziggy is a shy border collie mix who needs a patient sitter.', NULL, 0, 0, 1, 'pet-owner', 0, '2021-12-20 07:30:00', 'cce748f1-5e01-11f1-9246-581122c548ef'),
(10, 'laura_martin', 'Laura', 'Martin', 'laura.martin@example.com', '555-301-2004', '$2y$12$8gRse.wEIuFycCYIbBWXGu4xOh6Jmrw3qL6ffBjl5bXj2/iOzwpje', 'Parrot parent — Kiwi is a 12-year-old African Grey who needs an experienced handler.', NULL, 0, 0, 1, 'pet-owner', 0, '2023-05-08 11:00:00', 'cce74968-5e01-11f1-9246-581122c548ef'),
(11, 'tom_baker', 'Tom', 'Baker', 'tom.baker@example.com', '555-301-2005', '$2y$12$BSQDfIcDbis8PtcTycDcF.emng0/K.gYhjluvD/Qkcla.TcA6Wr4K', 'Owner of three dachshunds: Pretzel, Sausage, and Wurst. Yes, they are as chaotic as they sound.', NULL, 0, 0, 1, 'pet-owner', 0, '2022-04-17 14:00:00', 'cce749e1-5e01-11f1-9246-581122c548ef');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `animal`
--
ALTER TABLE `animal`
  ADD PRIMARY KEY (`animalID`),
  ADD KEY `fk_Animal_User_idx` (`User_userID`);

--
-- Index pour la table `application`
--
ALTER TABLE `application`
  ADD PRIMARY KEY (`appID`),
  ADD KEY `fk_Application_User1_idx` (`User_userID`),
  ADD KEY `fk_Application_Post1_idx` (`Post_postID`);

--
-- Index pour la table `cgu_versions`
--
ALTER TABLE `cgu_versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `version_number` (`version_number`,`section_title`),
  ADD KEY `idx_cgu_active` (`is_active`);

--
-- Index pour la table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `faq`
--
ALTER TABLE `faq`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Index pour la table `faq_category`
--
ALTER TABLE `faq_category`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Index pour la table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Index pour la table `post`
--
ALTER TABLE `post`
  ADD PRIMARY KEY (`postID`),
  ADD KEY `fk_Post_User1_idx` (`User_userID`);

--
-- Index pour la table `post_has_animal`
--
ALTER TABLE `post_has_animal`
  ADD PRIMARY KEY (`Post_postID`,`Animal_animalID`),
  ADD KEY `fk_Post_has_Animal_Animal1_idx` (`Animal_animalID`),
  ADD KEY `fk_Post_has_Animal_Post1_idx` (`Post_postID`);

--
-- Index pour la table `rating`
--
ALTER TABLE `rating`
  ADD PRIMARY KEY (`ratingID`),
  ADD KEY `fk_Rating_User1_idx` (`Rated_userID`),
  ADD KEY `fk_Rating_User2_idx` (`Rater_userID`);

--
-- Index pour la table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `selector` (`selector`);

--
-- Index pour la table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_review` (`rater_user_id`,`rated_user_id`),
  ADD KEY `idx_rated_user` (`rated_user_id`),
  ADD KEY `idx_is_disabled` (`is_disabled`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `public_id` (`public_id`),
  ADD KEY `idx_is_banned` (`is_banned`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `animal`
--
ALTER TABLE `animal`
  MODIFY `animalID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `application`
--
ALTER TABLE `application`
  MODIFY `appID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `cgu_versions`
--
ALTER TABLE `cgu_versions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `faq`
--
ALTER TABLE `faq`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT pour la table `faq_category`
--
ALTER TABLE `faq_category`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `post`
--
ALTER TABLE `post`
  MODIFY `postID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `rating`
--
ALTER TABLE `rating`
  MODIFY `ratingID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `animal`
--
ALTER TABLE `animal`
  ADD CONSTRAINT `fk_Animal_User` FOREIGN KEY (`User_userID`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `application`
--
ALTER TABLE `application`
  ADD CONSTRAINT `fk_Application_Post1` FOREIGN KEY (`Post_postID`) REFERENCES `post` (`postID`),
  ADD CONSTRAINT `fk_Application_User1` FOREIGN KEY (`User_userID`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `faq`
--
ALTER TABLE `faq`
  ADD CONSTRAINT `fk_faq_category` FOREIGN KEY (`category_id`) REFERENCES `faq_category` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `post`
--
ALTER TABLE `post`
  ADD CONSTRAINT `fk_Post_User1` FOREIGN KEY (`User_userID`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `post_has_animal`
--
ALTER TABLE `post_has_animal`
  ADD CONSTRAINT `fk_Post_has_Animal_Animal1` FOREIGN KEY (`Animal_animalID`) REFERENCES `animal` (`animalID`),
  ADD CONSTRAINT `fk_Post_has_Animal_Post1` FOREIGN KEY (`Post_postID`) REFERENCES `post` (`postID`);

--
-- Contraintes pour la table `rating`
--
ALTER TABLE `rating`
  ADD CONSTRAINT `fk_Rating_User1` FOREIGN KEY (`Rated_userID`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_Rating_User2` FOREIGN KEY (`Rater_userID`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD CONSTRAINT `fk_Remember_Tokens_User` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_Reviews_Rated_User` FOREIGN KEY (`rated_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_Reviews_Rater_User` FOREIGN KEY (`rater_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
