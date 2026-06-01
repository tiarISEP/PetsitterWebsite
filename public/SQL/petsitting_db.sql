-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mar. 12 mai 2026 à 19:03
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
-- Base de données : `petsitting_db`
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
-- Structure de la table `user`
--

CREATE TABLE `user` (
  `userID` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(45) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

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
-- Index pour la table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`userID`),
  ADD UNIQUE KEY `email_UNIQUE` (`email`);

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
-- AUTO_INCREMENT pour la table `user`
--
ALTER TABLE `user`
  MODIFY `userID` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `animal`
--
ALTER TABLE `animal`
  ADD CONSTRAINT `fk_Animal_User` FOREIGN KEY (`User_userID`) REFERENCES `user` (`userID`);

--
-- Contraintes pour la table `application`
--
ALTER TABLE `application`
  ADD CONSTRAINT `fk_Application_Post1` FOREIGN KEY (`Post_postID`) REFERENCES `post` (`postID`),
  ADD CONSTRAINT `fk_Application_User1` FOREIGN KEY (`User_userID`) REFERENCES `user` (`userID`);

--
-- Contraintes pour la table `post`
--
ALTER TABLE `post`
  ADD CONSTRAINT `fk_Post_User1` FOREIGN KEY (`User_userID`) REFERENCES `user` (`userID`);

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
  ADD CONSTRAINT `fk_Rating_User1` FOREIGN KEY (`Rated_userID`) REFERENCES `user` (`userID`),
  ADD CONSTRAINT `fk_Rating_User2` FOREIGN KEY (`Rater_userID`) REFERENCES `user` (`userID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
