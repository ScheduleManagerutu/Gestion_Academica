-- `BD Web`.Docente definition

CREATE TABLE `Docente` (
  `id` int NOT NULL AUTO_INCREMENT,
  `Documento` varchar(20) NOT NULL,
  `Materia` varchar(100) NOT NULL,
  `Telefono` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 
COLLATE=utf8mb4_0900_ai_ci 

#---------------------------------------------

CREATE TABLE `BD Web`.Administrativo (
	IDadministrativo INT auto_increment NOT NULL,
	Nombre varchar(50) NOT NULL,
	Apellido varchar(50) NOT NULL,
	Correo varchar(100) NOT NULL,
	Contraseña varchar(225) NOT NULL,
	CONSTRAINT Administrativo_PK PRIMARY KEY (IDadministrativo),
	CONSTRAINT Administrativo_UNIQUE UNIQUE KEY (Correo)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;
#--------------------------
CREATE TABLE `BD Web`.Materia (
	IDmateria INT auto_increment NOT NULL,
	Nombre varchar(100) NOT NULL,
	Horas_semana INT NOT NULL CHECK (Horas_semana >= 0),
	CONSTRAINT Materia_PK PRIMARY KEY (IDmateria)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;
#------------------------
CREATE TABLE `Usuario` (
  `IDusuario` int NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(50) NOT NULL,
  `Apellido` varchar(50) NOT NULL,
  `Correo` varchar(100) NOT NULL,
  PRIMARY KEY (`IDusuario`),
  UNIQUE KEY `Usuario_UNIQUE` (`Correo`)
) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 
COLLATE=utf8mb4_0900_ai_ci;