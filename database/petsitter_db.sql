-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mar. 12 mai 2026 à 22:26
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

-- --------------------------------------------------------

--
-- Structure de la table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `rater_user_id` int(11) NOT NULL COMMENT 'User who wrote the review',
  `rated_user_id` int(11) NOT NULL COMMENT 'User being reviewed',
  `rating` tinyint(1) NOT NULL COMMENT '1 to 5 stars',
  `review_text` varchar(250) DEFAULT NULL,
  `is_disabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = visible, 1 = hidden by admin',
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
(14, 11, 3, 4, 'Léo was patient and structured with the boys. They responded really well to his training approach. Wurst even learned sit properly!', 0, '2024-09-11 12:00:00', '2026-05-12 20:07:49');

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
  `user_type` enum('pet-owner','pet-sitter','admin') NOT NULL DEFAULT 'pet-owner',
  `is_banned` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = active, 1 = banned',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `username`, `first_name`, `last_name`, `email`, `phone`, `password`, `bio`, `avatar_url`, `user_type`, `is_banned`, `created_at`) VALUES
(1, 'admin', 'Admin', 'Admin', 'admin.g7b@gmail.com', NULL, '$2y$12$1wt30tZ1jGpd.ACHuukmleb7RVHO1rFbLeOeq0znXFTLZDda6moga', NULL, NULL, 'admin', 0, '2026-05-12 17:42:25'),
(2, 'sarah_pawsome', 'Sarah', 'Mitchell', 'sarah.mitchell@example.com', '555-201-1001', '$2y$12$bK0nvkH1JJQFVyyYBWppZOeoWwnM.bhruivYO6BSAZVD9NYM.NHvi', 'Passionate animal lover with 6 years of hands-on experience caring for dogs, cats, birds, and small animals. I treat every pet like my own and send daily photo updates to owners.', NULL, 'pet-sitter', 0, '2022-03-15 08:00:00'),
(3, 'leo_petcare', 'Léo', 'Dubois', 'leo.dubois@example.com', '555-201-1002', '$2y$12$4R5IblppwMCX43UCrphKtuHiN6o/y/8qd0U2Ro76SQvbhGZ2/7NWi', 'Certified animal behaviourist and full-time pet sitter based in the city. Specialist in anxious and reactive dogs. Fluent in French and English.', NULL, 'pet-sitter', 0, '2021-07-22 08:30:00'),
(4, 'maya_tails', 'Maya', 'Johnson', 'maya.johnson@example.com', '555-201-1003', '$2y$12$2ADnpaOsiDESpC9o.vSge.zHQ7nNlJ/m7Dgq3AAF1Id3HcerLqSuO', 'Veterinary nurse by trade, pet sitter by passion! I can handle medication administration, post-op care, and special diets. Your senior pets are very welcome.', NULL, 'pet-sitter', 0, '2023-01-10 07:00:00'),
(5, 'jake_walkies', 'Jake', 'Thompson', 'jake.thompson@example.com', '555-201-1004', '$2y$12$9eED0Cyjy06kXpCntSjt0Omgm86FmEgz3a8r2KQ3AeKtzKVXHI4Vq', 'Dog trainer and avid hiker — I make sure your pup gets plenty of exercise and stimulation every single day. Large breeds and high-energy dogs are my speciality.', NULL, 'pet-sitter', 0, '2022-09-05 09:15:00'),
(6, 'nina_feathers', 'Nina', 'Rossi', 'nina.rossi@example.com', '555-201-1005', '$2y$12$W3sQWShg2fTeu09ThrTYHuOwHEIp67T/2DaG2eVKJJ2ts6KYs/Xoy', 'Exotic pet specialist — birds, reptiles, rabbits, guinea pigs, and fish are all in my wheelhouse. I have cared for over 40 species in the past 5 years.', NULL, 'pet-sitter', 0, '2020-11-30 13:00:00'),
(7, 'mike_chen', 'Mike', 'Chen', 'mike.chen@example.com', '555-301-2001', '$2y$12$/Cg5YHgMV5P7SBOPKPR92e6MsmE/IzQhzS9oB7hPDz5cRUNn5FRVe', 'Owner of Max, a 3-year-old Golden Retriever who loves fetch and belly rubs.', NULL, 'pet-owner', 0, '2023-02-14 08:00:00'),
(8, 'emma_wilson', 'Emma', 'Wilson', 'emma.wilson@example.com', '555-301-2002', '$2y$12$WBnINoz0F7t1.hJ8Vqn3Ce6GQHFWVVYwGrIPV5TjR80lFe4qAQ7ly', 'Cat mum to Biscuit and Cream, two tabby brothers who rule the flat.', NULL, 'pet-owner', 0, '2022-06-01 08:00:00'),
(9, 'david_park', 'David', 'Park', 'david.park@example.com', '555-301-2003', '$2y$12$5btj2x2uN/pqJmObubQR2.HNIw7Y6ticA37VIKAiYoMHbiA7V18wK', 'Rescue dog advocate. My pup Ziggy is a shy border collie mix who needs a patient sitter.', NULL, 'pet-owner', 0, '2021-12-20 07:30:00'),
(10, 'laura_martin', 'Laura', 'Martin', 'laura.martin@example.com', '555-301-2004', '$2y$12$8gRse.wEIuFycCYIbBWXGu4xOh6Jmrw3qL6ffBjl5bXj2/iOzwpje', 'Parrot parent — Kiwi is a 12-year-old African Grey who needs an experienced handler.', NULL, 'pet-owner', 0, '2023-05-08 11:00:00'),
(11, 'tom_baker', 'Tom', 'Baker', 'tom.baker@example.com', '555-301-2005', '$2y$12$BSQDfIcDbis8PtcTycDcF.emng0/K.gYhjluvD/Qkcla.TcA6Wr4K', 'Owner of three dachshunds: Pretzel, Sausage, and Wurst. Yes, they are as chaotic as they sound.', NULL, 'pet-owner', 0, '2022-04-17 14:00:00');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
