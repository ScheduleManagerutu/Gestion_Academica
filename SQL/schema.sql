CREATE DATABASE `BD Web` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

-- `BD Web`.Docente definition

CREATE TABLE `Docente` (
  `IDdocente` int NOT NULL AUTO_INCREMENT,
  `Documento` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `Materia` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `Telefono` varchar(20) DEFAULT NULL,
  `Nombre` varchar(100) NOT NULL,
  PRIMARY KEY (`IDdocente`),
  CONSTRAINT `Docente_Materia_FK` FOREIGN KEY (`IDdocente`) REFERENCES `Materia` (`IDmateria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Tabla de información docente';

-- `BD Web`.Gestion_Usuario_Docente definition

CREATE TABLE `Gestion_Usuario_Docente` (
  `IDusuario` int NOT NULL,
  `IDdocente` int NOT NULL,
  PRIMARY KEY (`IDusuario`,`IDdocente`),
  KEY `FK_Docente` (`IDdocente`),
  CONSTRAINT `FK_Docente` FOREIGN KEY (`IDdocente`) REFERENCES `Docente` (`IDdocente`),
  CONSTRAINT `FK_Usuario` FOREIGN KEY (`IDusuario`) REFERENCES `Usuario` (`IDusuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- `BD Web`.Gestion_Usuario_Grupo definition

CREATE TABLE `Gestion_Usuario_Grupo` (
  `IDusuario` int NOT NULL,
  `IDgrupo` int NOT NULL,
  PRIMARY KEY (`IDusuario`,`IDgrupo`),
  KEY `FK_IDgrupo` (`IDgrupo`),
  CONSTRAINT `FK_IDgrupo` FOREIGN KEY (`IDgrupo`) REFERENCES `Grupo` (`IDgrupo`),
  CONSTRAINT `FK_IDusuario` FOREIGN KEY (`IDusuario`) REFERENCES `Usuario` (`IDusuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- `BD Web`.Gestion_Usuario_Materia definition

CREATE TABLE `Gestion_Usuario_Materia` (
  `IDusuario` int NOT NULL,
  `IDmateria` int NOT NULL,
  PRIMARY KEY (`IDusuario`,`IDmateria`),
  KEY `FK_IDmateria` (`IDmateria`),
  CONSTRAINT `FK_IDmateria` FOREIGN KEY (`IDmateria`) REFERENCES `Materia` (`IDmateria`),
  CONSTRAINT `FK_IDuusuario` FOREIGN KEY (`IDusuario`) REFERENCES `Usuario` (`IDusuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- `BD Web`.Grupo definition

CREATE TABLE `Grupo` (
  `IDgrupo` int NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(100) NOT NULL,
  `Cantidad_alumnos` int NOT NULL,
  `IDdocente` int NOT NULL,
  PRIMARY KEY (`IDgrupo`),
  CONSTRAINT `Grupo_Docente_FK` FOREIGN KEY (`IDgrupo`) REFERENCES `Docente` (`IDdocente`),
  CONSTRAINT `alumnos_chek` CHECK ((`Cantidad_alumnos` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- `BD Web`.Materia definition

CREATE TABLE `Materia` (
  `IDmateria` int NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(100) NOT NULL,
  `Horas_semana` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`IDmateria`),
  CONSTRAINT `Materia_chk_1` CHECK ((`Horas_semana` >= '0000-00-00 00:00:00'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- `BD Web`.Turnos definition

CREATE TABLE `Turnos` (
  `IDTurno` int NOT NULL,
  `Cursos` varchar(100) NOT NULL,
  `Horarios` datetime NOT NULL,
  `Hora_inicio` datetime NOT NULL,
  `Hora_fin` datetime NOT NULL,
  `Dia` date NOT NULL,
  PRIMARY KEY (`IDTurno`),
  CONSTRAINT `Turnos_Materia_FK` FOREIGN KEY (`IDTurno`) REFERENCES `Materia` (`IDmateria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- `BD Web`.Usuario definition

CREATE TABLE `Usuario` (
  `IDusuario` int NOT NULL AUTO_INCREMENT,
  `Correo` varchar(100) NOT NULL,
  `Contraseña` varchar(225) NOT NULL,
  `Usuario` varchar(100) NOT NULL,
  PRIMARY KEY (`IDusuario`),
  UNIQUE KEY `Administrativo_UNIQUE` (`Correo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE Clases_Asignadas (
    IDclase INT AUTO_INCREMENT PRIMARY KEY,
    IDgrupo INT,
    Dia VARCHAR(20) NOT NULL,
    Hora_inicio TIME NOT NULL,
    Hora_fin TIME NOT NULL,
    IDmateria INT,
    IDdocente INT,
    FOREIGN KEY (IDgrupo) REFERENCES Grupo(IDgrupo),
    FOREIGN KEY (IDmateria) REFERENCES Materia(IDmateria),
    FOREIGN KEY (IDdocente) REFERENCES Docente(IDdocente)
);
