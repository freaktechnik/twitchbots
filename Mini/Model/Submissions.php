<?php

namespace Mini\Model;

class Submissions extends PaginatingStore {
    const SUBMISSION = 0;
    const CORRECTION = 1;

    function __construct(PingablePDO $db, int $pageSize = 50)
    {
        parent::__construct($db, "submissions", $pageSize);
    }

    public function append(string $username, $type, int $correction = self::SUBMISSION, string $channel = NULL)
    {
        $query = $this->prepareInsert("(name,description,type,channel) VALUES (?,?,?,?)");
        $params = array($username, $type, $correction, $channel);

        $query->execute($params);
    }

    public function getSubmissions(): array
    {
        $query = $this->prepareSelect("*", "ORDER BY date DESC");
        $query->execute();

        return $query->fetchAll();
    }

    public function has(string $username, int $type = NULL, string $description = NULL)
    {
        $where = "WHERE name=?";
        $params = array($username);

        if(!empty($type)) {
            $where .= " AND type=?";
            $params[] = $type;
        }
        if(!empty($description)) {
            $where .= " AND description=?";
            $params[] = $description;
        }
        $query = $this->prepareSelect("*", $where);
        $query->execute($params);

        return !empty($query->fetch());
    }

    public function hasSubmission(string $username): bool
    {
        return $this->has($username, self::SUBMISSION);
    }


    public function hasCorrection(string $username, string $description): bool
    {
        return $this->has($username, self::CORRECTION, $description);
    }


    public function setInChat(int $id, $inChannel, bool $live)
    {
        if($live) {
            $sql = "online=? WHERE id=?";
        }
        else {
            $sql = "offline=? WHERE id=?";
        }

	    $query = $this->prepareUpdate($sql);
	    $query->execute(array($inChannel, $id));
    }

    public function setModded(int $id, $isMod)
    {
        $sql = "ismod=? WHERE id=?";
        $query = $this->prepareUpdate($sql);
        $query->execute(array($isMod, $id));
    }

    public function setFollowing(int $id, $followingCount = null)
    {
        $sql = "following=? WHERE id=?";
        $query = $this->db->prepareUpdate($sql);
        $query->execute(array($followingCount, $id));
    }

    public function setFollowingChannel(int $id, $followingChannel = null)
    {
        $sql = "following_channel=? WHERE id=?";
        $query = $this->db->prepareUpdate($sql);
        $query->execute(array($followingChannel, $id));
    }

    public function removeSubmission(int $id)
    {
        $query = $this->prepareDelete("WHERE id=?");
        $query->execute(array($id));
    }

    public function removeSubmissions(string $username, string $description = NULL) {
        $condition = "WHERE name=?";
        $args = array($username);
        if(!empty($description)) {
            $condition .= " AND description=?";
            $args[] = $description;
        }
        $query = $this->preapreDelete($condition);
        $query->execute($args);
    }
}
