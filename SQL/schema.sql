-- `BD Web`.Docente definition

CREATE TABLE `Docente` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Tabla de información docente';

ALTER TABLE `BD Web`.Docente CHANGE nombre Documento varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL;
ALTER TABLE `BD Web`.Docente MODIFY COLUMN Documento varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL;
ALTER TABLE `BD Web`.Docente CHANGE email Materia varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL;
ALTER TABLE `BD Web`.Docente MODIFY COLUMN Materia varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL;
ALTER TABLE `BD Web`.Docente ADD Telefono varchar(20) NULL;

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
