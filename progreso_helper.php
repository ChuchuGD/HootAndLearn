<?php
/**
 * Helper para actualizar el progreso del estudiante en un curso
 * Se llama después de calificar una actividad
 */

function actualizarProgresoEstudiante($conn, $estudianteID, $cursoID) {
    // 1. Contar total de actividades del curso
    $sqlTotal = "SELECT COUNT(*) as total FROM actividades WHERE CursoID = ? AND Activo = TRUE";
    $stmtTotal = $conn->prepare($sqlTotal);
    if (!$stmtTotal) {
        error_log("Error preparando consulta total actividades: " . $conn->error);
        return false;
    }
    
    $stmtTotal->bind_param("i", $cursoID);
    $stmtTotal->execute();
    $resultTotal = $stmtTotal->get_result();
    $rowTotal = $resultTotal->fetch_assoc();
    $totalActividades = (int)$rowTotal['total'];
    $stmtTotal->close();
    
    if ($totalActividades === 0) {
        // Si no hay actividades, el progreso se mantiene en 0
        return true;
    }
    
    // 2. Contar actividades calificadas del estudiante en este curso
    $sqlCalificadas = "
        SELECT COUNT(DISTINCT e.ActividadID) as calificadas
        FROM entregas e
        INNER JOIN actividades a ON e.ActividadID = a.ActividadID
        WHERE e.EstID = ? 
        AND a.CursoID = ? 
        AND e.Estado = 'calificado'
        AND e.Calificacion IS NOT NULL
    ";
    
    $stmtCalificadas = $conn->prepare($sqlCalificadas);
    if (!$stmtCalificadas) {
        error_log("Error preparando consulta actividades calificadas: " . $conn->error);
        return false;
    }
    
    $stmtCalificadas->bind_param("ii", $estudianteID, $cursoID);
    $stmtCalificadas->execute();
    $resultCalificadas = $stmtCalificadas->get_result();
    $rowCalificadas = $resultCalificadas->fetch_assoc();
    $actividadesCalificadas = (int)$rowCalificadas['calificadas'];
    $stmtCalificadas->close();
    
    // 3. Calcular progreso (porcentaje)
    $progreso = round(($actividadesCalificadas / $totalActividades) * 100);
    
    // 4. Actualizar tabla inscripciones
    $sqlUpdate = "
        UPDATE inscripciones 
        SET Progreso = ?, 
            LeccionesCompletadas = ?
        WHERE EstID = ? 
        AND CursoID = ?
    ";
    
    $stmtUpdate = $conn->prepare($sqlUpdate);
    if (!$stmtUpdate) {
        error_log("Error preparando actualización de progreso: " . $conn->error);
        return false;
    }
    
    $stmtUpdate->bind_param("iiii", $progreso, $actividadesCalificadas, $estudianteID, $cursoID);
    $resultado = $stmtUpdate->execute();
    
    if (!$resultado) {
        error_log("Error actualizando progreso: " . $stmtUpdate->error);
        $stmtUpdate->close();
        return false;
    }
    
    $stmtUpdate->close();
    
    // Log para debugging
    error_log("Progreso actualizado: EstudianteID=$estudianteID, CursoID=$cursoID, Progreso=$progreso% ($actividadesCalificadas/$totalActividades)");
    
    return true;
}

/**
 * Función alternativa que recalcula el progreso para todos los estudiantes de un curso
 * Útil para corregir inconsistencias o inicializar datos
 */
function recalcularProgresoCurso($conn, $cursoID) {
    // Obtener todos los estudiantes inscritos en el curso
    $sqlEstudiantes = "SELECT DISTINCT EstID FROM inscripciones WHERE CursoID = ?";
    $stmt = $conn->prepare($sqlEstudiantes);
    
    if (!$stmt) {
        error_log("Error preparando consulta estudiantes: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("i", $cursoID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $actualizados = 0;
    $errores = 0;
    
    while ($row = $result->fetch_assoc()) {
        $estudianteID = (int)$row['EstID'];
        if (actualizarProgresoEstudiante($conn, $estudianteID, $cursoID)) {
            $actualizados++;
        } else {
            $errores++;
        }
    }
    
    $stmt->close();
    
    error_log("Recálculo completado para curso $cursoID: $actualizados actualizados, $errores errores");
    
    return ['actualizados' => $actualizados, 'errores' => $errores];
}
?>